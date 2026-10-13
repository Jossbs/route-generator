<?php

declare(strict_types=1);

namespace App\Services\Kpis;

use App\Enums\Kpis\SalesEventSource;
use App\Enums\Kpis\SalesEventStatus;
use App\Models\SalesEvent;
use App\Models\SalesEventType;
use App\Models\User;
use App\Services\Kpis\Sources\SuggestedEvent;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Todo lo que le pasa a un evento: capturarlo, corregirlo, anularlo, confirmar o
 * descartar una sugerencia.
 *
 * Es un servicio y no lógica de controlador por la misma razón que la generación
 * de rutas (docs/ARQUITECTURA.md §3): hoy lo llama el formulario del administrador y mañana
 * lo llamará la importación de Kommo o del CRM propio, sin reescribir nada.
 *
 * Aquí vive la regla del cierre: si el evento cae —o caía— en un mes cerrado, el
 * cambio exige motivo y queda marcado como ajuste.
 */
final class SalesEventRecorder
{
    public function __construct(
        private readonly EventValues $values,
        private readonly ClosedPeriods $closed,
    ) {}

    /** @param  array<string, mixed>  $values */
    public function record(
        SalesEventType $type,
        User $advisor,
        CarbonImmutable $occurredOn,
        array $values,
        User $by,
        ?string $notes = null,
        ?string $adjustmentReason = null,
    ): SalesEvent {
        $this->assertActive($type);
        $ajuste = $this->closed->guard($adjustmentReason, $occurredOn);

        return SalesEvent::create([
            'sales_event_type_id' => $type->id,
            'user_id' => $advisor->id,
            'occurred_on' => $occurredOn->toDateString(),
            'values' => $this->values->normalize($type, $values),
            'notes' => $notes,
            'source' => SalesEventSource::Manual,
            'status' => SalesEventStatus::Confirmado,
            'created_by' => $by->id,
            'confirmed_by' => $by->id,
            'confirmed_at' => now(),
            'is_adjustment' => $ajuste,
            'adjustment_reason' => $ajuste ? $adjustmentReason : null,
        ]);
    }

    /** @param  array<string, mixed>  $values */
    public function update(
        SalesEvent $event,
        User $advisor,
        CarbonImmutable $occurredOn,
        array $values,
        ?string $notes = null,
        ?string $adjustmentReason = null,
    ): SalesEvent {
        $this->assertEditable($event);
        $ajuste = $this->closed->guard($adjustmentReason, $event->occurred_on, $occurredOn);

        $event->update([
            'user_id' => $advisor->id,
            'occurred_on' => $occurredOn->toDateString(),
            'values' => $this->values->normalize($event->type, $values),
            'notes' => $notes,
            ...($ajuste ? ['is_adjustment' => true, 'adjustment_reason' => $adjustmentReason] : []),
        ]);

        return $event;
    }

    /** Anular no borra: el evento deja de contar y se queda con quién, cuándo y por qué. */
    public function void(SalesEvent $event, User $by, string $reason): SalesEvent
    {
        $this->assertEditable($event);
        $ajuste = $this->closed->guard($reason, $event->occurred_on);

        $event->update([
            'voided_at' => now(),
            'voided_by' => $by->id,
            'void_reason' => $reason,
            ...($ajuste ? ['is_adjustment' => true, 'adjustment_reason' => $reason] : []),
        ]);

        return $event;
    }

    public function confirm(SalesEvent $event, User $by, ?string $adjustmentReason = null): SalesEvent
    {
        $this->assertSuggested($event);
        $ajuste = $this->closed->guard($adjustmentReason, $event->occurred_on);

        $event->update([
            'status' => SalesEventStatus::Confirmado,
            'confirmed_by' => $by->id,
            'confirmed_at' => now(),
            ...($ajuste ? ['is_adjustment' => true, 'adjustment_reason' => $adjustmentReason] : []),
        ]);

        return $event;
    }

    public function discard(SalesEvent $event, User $by): SalesEvent
    {
        $this->assertSuggested($event);

        $event->update([
            'status' => SalesEventStatus::Descartado,
            'confirmed_by' => $by->id,
            'confirmed_at' => now(),
        ]);

        return $event;
    }

    /**
     * Lo que propone una fuente automática, sin contarlo todavía.
     *
     * Es idempotente por fuente y folio externo: la misma membresía sugerida dos
     * veces es un solo evento. Mientras siga por confirmar se actualiza con lo
     * último que diga la fuente; ya confirmado o descartado, la fuente no lo toca,
     * porque la decisión del administrador manda.
     */
    public function suggest(SuggestedEvent $suggestion): ?SalesEvent
    {
        $tipo = SalesEventType::query()->with('fields')->where('slug', $suggestion->eventTypeSlug)->first();

        if ($tipo === null || ! $tipo->is_active) {
            return null;
        }

        $claves = $tipo->fields->pluck('key')->all();
        $valores = array_intersect_key($suggestion->values, array_flip($claves));

        $existente = SalesEvent::query()
            ->where('source', $suggestion->source->value)
            ->where('external_id', $suggestion->externalId)
            ->first();

        if ($existente !== null) {
            if ($existente->status === SalesEventStatus::Sugerido) {
                $existente->update([
                    'user_id' => $suggestion->advisorId,
                    'occurred_on' => $suggestion->occurredOn->toDateString(),
                    'values' => $valores,
                    'notes' => $suggestion->notes,
                ]);
            }

            return $existente;
        }

        return SalesEvent::create([
            'sales_event_type_id' => $tipo->id,
            'user_id' => $suggestion->advisorId,
            'occurred_on' => $suggestion->occurredOn->toDateString(),
            'values' => $valores,
            'notes' => $suggestion->notes,
            'source' => $suggestion->source,
            'external_id' => $suggestion->externalId,
            'status' => SalesEventStatus::Sugerido,
        ]);
    }

    private function assertActive(SalesEventType $type): void
    {
        if (! $type->is_active) {
            throw ValidationException::withMessages(['sales_event_type_id' => 'Ese tipo de evento está desactivado.']);
        }
    }

    private function assertEditable(SalesEvent $event): void
    {
        if ($event->isVoided()) {
            throw ValidationException::withMessages(['event' => 'El evento ya está anulado.']);
        }
    }

    private function assertSuggested(SalesEvent $event): void
    {
        if ($event->status !== SalesEventStatus::Sugerido) {
            throw ValidationException::withMessages(['event' => 'Ese evento ya no está por confirmar.']);
        }
    }
}
