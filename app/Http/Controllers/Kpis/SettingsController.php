<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kpis;

use App\Enums\Kpis\FormulaOperator;
use App\Enums\Kpis\MeasureAggregation;
use App\Enums\Kpis\MeasureUnit;
use App\Enums\Kpis\SalesFieldType;
use App\Enums\Kpis\SalesRole;
use App\Http\Controllers\Controller;
use App\Models\Kpi;
use App\Models\KpiMeasure;
use App\Models\SalesAssignment;
use App\Models\SalesEventField;
use App\Models\SalesEventType;
use App\Models\SalesTeam;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La configuración del módulo: qué se registra, qué se mide y quién es quién.
 *
 * Es la razón de que un KPI nuevo sea un alta y no un deploy (decidido el
 * 2026-09-26). Tres pestañas: tipos de evento, KPIs y equipos.
 */
class SettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('kpis/configuracion', [
            'tipos' => SalesEventType::query()->with('fields')->orderBy('name')->get()->map(fn (SalesEventType $t): array => [
                'id' => $t->id,
                'slug' => $t->slug,
                'name' => $t->name,
                'description' => $t->description,
                'is_active' => $t->is_active,
                'events_count' => $t->events()->count(),
                'fields' => $t->fields->map(fn (SalesEventField $f): array => [
                    'id' => $f->id,
                    'key' => $f->key,
                    'label' => $f->label,
                    'data_type' => $f->data_type->value,
                    'options' => $f->options,
                    'is_required' => $f->is_required,
                ])->all(),
            ]),
            'kpis' => Kpi::query()->with('measures')->orderBy('display_order')->orderBy('name')->get()->map(fn (Kpi $k): array => [
                'id' => $k->id,
                'name' => $k->name,
                'description' => $k->description,
                'display_order' => $k->display_order,
                'is_active' => $k->is_active,
                'measures' => $k->measures->map(fn (KpiMeasure $m): array => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'unit' => $m->unit->value,
                    'kind' => $m->kind->value,
                    'sales_event_type_id' => $m->sales_event_type_id,
                    'aggregation' => $m->aggregation?->value,
                    'field_key' => $m->field_key,
                    'filters' => $m->filters,
                    'formula_operator' => $m->formula_operator?->value,
                    'left_measure_id' => $m->left_measure_id,
                    'right_measure_id' => $m->right_measure_id,
                    'has_target' => $m->has_target,
                    'higher_is_better' => $m->higher_is_better,
                ])->all(),
            ]),
            'medidas' => KpiMeasure::query()->with('kpi:id,name')->get()->map(fn (KpiMeasure $m): array => [
                'value' => (string) $m->id,
                'label' => $m->kpi->name.' · '.$m->name,
            ]),
            'equipos' => SalesTeam::query()->orderBy('name')->get()->map(fn (SalesTeam $t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'is_active' => $t->is_active,
            ]),
            'asignaciones' => SalesAssignment::query()
                ->with(['user:id,name', 'team:id,name'])
                ->orderByRaw('ends_on is not null')
                ->orderByDesc('starts_on')
                ->get()
                ->map(fn (SalesAssignment $a): array => [
                    'id' => $a->id,
                    'user_id' => $a->user_id,
                    'user' => $a->user->name,
                    'sales_team_id' => $a->sales_team_id,
                    'team' => $a->team?->name,
                    'role' => $a->role->value,
                    'role_label' => $a->role->label(),
                    'starts_on' => $a->starts_on->toDateString(),
                    'ends_on' => $a->ends_on?->toDateString(),
                ]),
            'usuarios' => User::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u): array => ['value' => (string) $u->id, 'label' => $u->name]),
            'opciones' => [
                'tiposDeDato' => SalesFieldType::options(),
                'agregaciones' => MeasureAggregation::options(),
                'operadores' => FormulaOperator::options(),
                'unidades' => MeasureUnit::options(),
                'roles' => SalesRole::options(),
            ],
        ]);
    }
}
