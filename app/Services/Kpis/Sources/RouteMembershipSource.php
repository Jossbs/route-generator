<?php

declare(strict_types=1);

namespace App\Services\Kpis\Sources;

use App\Enums\Kpis\SalesEventSource;
use App\Models\Membership;
use Carbon\CarbonImmutable;

/**
 * Las membresías que registra el generador de rutas, como ventas por confirmar.
 *
 * Cada vez que un asesor emite una ruta final, la membresía ya dice qué plan se
 * vendió y cuánto se cobró. Pedírselo otra vez al administrador sería capturar dos
 * veces lo mismo; contarlo solo sería contar ventas que nadie revisó. Por eso se
 * sugiere (decidido el 2026-09-26): aparece en la captura y el administrador la
 * confirma o la descarta.
 *
 * El precio sale de `memberships.price`, que nunca llega al snapshot ni al PDF
 * (docs/ARQUITECTURA.md §4.5): aquí es el registro de la venta, que es para lo que existe.
 */
final class RouteMembershipSource implements SalesSource
{
    public function source(): SalesEventSource
    {
        return SalesEventSource::Rutas;
    }

    public function since(CarbonImmutable $since): iterable
    {
        $membresias = Membership::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('created_by')
            ->orderBy('id')
            ->lazy();

        foreach ($membresias as $membresia) {
            yield $this->toEvent($membresia);
        }
    }

    public function toEvent(Membership $membership): SuggestedEvent
    {
        $fecha = CarbonImmutable::parse(
            ($membership->created_at ?? now())->setTimezone((string) config('kpis.timezone'))->toDateString(),
        );

        return new SuggestedEvent(
            source: $this->source(),
            externalId: 'membership:'.$membership->id,
            eventTypeSlug: (string) config('kpis.membership_event_type'),
            advisorId: (int) $membership->created_by,
            occurredOn: $fecha,
            values: array_filter([
                'plan' => $membership->plan?->value,
                'importe' => $membership->price === null ? null : (float) $membership->price,
                'cantidad' => 1,
            ], fn (mixed $v): bool => $v !== null),
            notes: trim($membership->full_name.($membership->plan_other ? ' · '.$membership->plan_other : '')),
        );
    }
}
