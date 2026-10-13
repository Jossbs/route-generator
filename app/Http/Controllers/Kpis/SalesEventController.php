<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kpis;

use App\Enums\Kpis\KpiPeriodType;
use App\Enums\Kpis\SalesEventStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Kpis\Concerns\RespondsWithToast;
use App\Models\KpiPeriodClosure;
use App\Models\SalesEvent;
use App\Models\SalesEventField;
use App\Models\SalesEventType;
use App\Models\User;
use App\Services\Kpis\Period;
use App\Services\Kpis\Reports\EventsCsv;
use App\Services\Kpis\SalesEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La captura: cada venta, cobro o acción, uno por uno (decidido el 2026-09-26).
 *
 * Arriba van las ventas que sugirió el generador de rutas, esperando que el
 * administrador las confirme; abajo, lo capturado del mes con sus filtros. Toda
 * la lógica —validar los campos, el cierre de mes, anular sin borrar— vive en
 * `SalesEventRecorder`; aquí solo se traduce la petición.
 */
class SalesEventController extends Controller
{
    use RespondsWithToast;

    public function index(Request $request): Response
    {
        $periodo = Period::fromInput(KpiPeriodType::Mes->value, $request->query('fecha'));
        $tipos = SalesEventType::query()->with('fields')->orderBy('name')->get();
        $filtros = [
            'tipo' => (string) $request->query('tipo', ''),
            'asesor' => (string) $request->query('asesor', ''),
            'estado' => (string) $request->query('estado', ''),
        ];

        $eventos = SalesEvent::query()
            ->with(['type.fields', 'advisor:id,name', 'creator:id,name'])
            ->where('status', '!=', SalesEventStatus::Sugerido->value)
            ->whereBetween('occurred_on', [$periodo->start->toDateString(), $periodo->end->toDateString()])
            ->when($filtros['tipo'] !== '', fn ($q) => $q->where('sales_event_type_id', (int) $filtros['tipo']))
            ->when($filtros['asesor'] !== '', fn ($q) => $q->where('user_id', (int) $filtros['asesor']))
            ->when($filtros['estado'] === 'anulados', fn ($q) => $q->whereNotNull('voided_at'))
            ->when($filtros['estado'] === 'ajustes', fn ($q) => $q->where('is_adjustment', true))
            ->when($filtros['estado'] === 'descartados', fn ($q) => $q->where('status', SalesEventStatus::Descartado->value))
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (SalesEvent $e): array => $this->present($e));

        $sugeridos = SalesEvent::query()
            ->with(['type.fields', 'advisor:id,name'])
            ->where('status', SalesEventStatus::Sugerido->value)
            ->orderBy('occurred_on')
            ->get()
            ->map(fn (SalesEvent $e): array => $this->present($e));

        return Inertia::render('kpis/eventos', [
            'periodo' => $periodo->toArray(),
            'anterior' => $periodo->previous()->start->toDateString(),
            'siguiente' => $periodo->next()->start->toDateString(),
            'filtros' => $filtros,
            'eventos' => $eventos,
            'sugeridos' => $sugeridos,
            'tipos' => $tipos->map(fn (SalesEventType $t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'is_active' => $t->is_active,
                'fields' => $t->fields->map(fn (SalesEventField $f): array => [
                    'key' => $f->key,
                    'label' => $f->label,
                    'data_type' => $f->data_type->value,
                    'options' => $f->options,
                    'is_required' => $f->is_required,
                ])->all(),
            ]),
            'asesores' => User::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u): array => ['value' => (string) $u->id, 'label' => $u->name]),
            'mesesCerrados' => KpiPeriodClosure::query()->orderBy('period_start')->get()
                ->map(fn (KpiPeriodClosure $c): string => $c->period_start->format('Y-m')),
            'hoy' => Period::today()->toDateString(),
        ]);
    }

    public function export(Request $request, EventsCsv $csv): StreamedResponse
    {
        $periodo = Period::fromInput($request->query('periodo', KpiPeriodType::Mes->value), $request->query('fecha'));
        $tipo = filled($request->query('tipo')) ? SalesEventType::query()->find((int) $request->query('tipo')) : null;

        return $csv->download($periodo, $tipo);
    }

    public function store(Request $request, SalesEventRecorder $recorder): RedirectResponse
    {
        $datos = $this->validated($request);

        $recorder->record(
            SalesEventType::query()->with('fields')->whereKey($datos['sales_event_type_id'])->firstOrFail(),
            User::query()->whereKey($datos['user_id'])->firstOrFail(),
            CarbonImmutable::parse($datos['occurred_on']),
            $datos['values'] ?? [],
            $request->user(),
            $datos['notes'] ?? null,
            $datos['adjustment_reason'] ?? null,
        );

        return $this->backWithToast('Evento registrado.');
    }

    public function update(Request $request, SalesEvent $event, SalesEventRecorder $recorder): RedirectResponse
    {
        $datos = $this->validated($request, withType: false);

        $recorder->update(
            $event->load('type.fields'),
            User::query()->whereKey($datos['user_id'])->firstOrFail(),
            CarbonImmutable::parse($datos['occurred_on']),
            $datos['values'] ?? [],
            $datos['notes'] ?? null,
            $datos['adjustment_reason'] ?? null,
        );

        return $this->backWithToast('Evento corregido.');
    }

    public function void(Request $request, SalesEvent $event, SalesEventRecorder $recorder): RedirectResponse
    {
        $datos = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => 'motivo']);

        $recorder->void($event, $request->user(), $datos['reason']);

        return $this->backWithToast('Evento anulado.');
    }

    public function confirm(Request $request, SalesEvent $event, SalesEventRecorder $recorder): RedirectResponse
    {
        $datos = $request->validate(['adjustment_reason' => ['nullable', 'string', 'max:500']]);

        $recorder->confirm($event, $request->user(), $datos['adjustment_reason'] ?? null);

        return $this->backWithToast('Venta confirmada.');
    }

    public function discard(Request $request, SalesEvent $event, SalesEventRecorder $recorder): RedirectResponse
    {
        $recorder->discard($event, $request->user());

        return $this->backWithToast('Sugerencia descartada.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $withType = true): array
    {
        return $request->validate([
            ...($withType ? ['sales_event_type_id' => ['required', 'integer', 'exists:sales_event_types,id']] : []),
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'occurred_on' => ['required', 'date'],
            'values' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'adjustment_reason' => ['nullable', 'string', 'max:500'],
        ], [], [
            'sales_event_type_id' => 'tipo de evento',
            'user_id' => 'asesor',
            'occurred_on' => 'fecha',
        ]);
    }

    /** @return array<string, mixed> */
    private function present(SalesEvent $e): array
    {
        return [
            'id' => $e->id,
            'type_id' => $e->sales_event_type_id,
            'type' => $e->type->name,
            'user_id' => $e->user_id,
            'advisor' => $e->advisor->name,
            'occurred_on' => $e->occurred_on->toDateString(),
            'values' => $e->values,
            // Lo que se lee en la lista: «Plan: Expert Anual · Importe: $4,199».
            'summary' => $e->type->fields
                ->filter(fn (SalesEventField $f): bool => array_key_exists($f->key, $e->values ?? []))
                ->map(fn (SalesEventField $f): string => $f->label.': '.$this->display($f, $e->values[$f->key]))
                ->implode(' · '),
            'notes' => $e->notes,
            'source' => $e->source->label(),
            'status' => $e->status->value,
            'is_adjustment' => $e->is_adjustment,
            'adjustment_reason' => $e->adjustment_reason,
            'voided' => $e->isVoided(),
            'void_reason' => $e->void_reason,
            'creator' => $e->creator?->name,
        ];
    }

    private function display(SalesEventField $field, mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'Sí' : 'No',
            $field->data_type->value === 'dinero' => '$'.number_format((float) $value, abs((float) $value - round((float) $value)) < 0.005 ? 0 : 2),
            $field->options !== [] => $field->optionLabel($value),
            default => (string) $value,
        };
    }
}
