<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kpis;

use App\Enums\Kpis\KpiPeriodType;
use App\Enums\Kpis\SalesRole;
use App\Enums\Kpis\TargetScope;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Kpis\Concerns\RespondsWithToast;
use App\Models\KpiMeasure;
use App\Models\KpiTarget;
use App\Models\SalesAssignment;
use App\Models\SalesTeam;
use App\Models\User;
use App\Services\Kpis\Period;
use App\Services\Kpis\Scope;
use App\Services\Kpis\TargetBook;
use App\Services\Kpis\TargetResolver;
use App\Services\Kpis\ValueFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Las metas de una medida en un trimestre: una fila por alcance —todo ventas,
 * cada equipo, cada asesor— y una columna por mes más la del trimestre.
 *
 * La meta del trimestre es opcional: si se deja vacía vale la suma de sus meses,
 * que es lo que la tabla muestra en gris como referencia.
 */
class TargetController extends Controller
{
    use RespondsWithToast;

    public function index(Request $request, TargetResolver $resolver): Response
    {
        $trimestre = Period::fromInput(KpiPeriodType::Trimestre->value, $request->query('fecha'));
        $medidas = KpiMeasure::query()
            ->with(['kpi', 'left', 'right'])
            ->where('has_target', true)
            ->whereHas('kpi', fn ($q) => $q->where('is_active', true))
            ->get()
            ->sortBy(fn (KpiMeasure $m): string => sprintf('%05d-%s-%05d', $m->kpi->display_order, $m->kpi->name, $m->position))
            ->values();

        $medida = $medidas->firstWhere('id', (int) $request->query('medida')) ?? $medidas->first();
        $meses = [$trimestre->start, $trimestre->start->addMonth(), $trimestre->start->addMonths(2)];

        return Inertia::render('kpis/metas', [
            'trimestre' => $trimestre->toArray(),
            'anterior' => $trimestre->previous()->start->toDateString(),
            'siguiente' => $trimestre->next()->start->toDateString(),
            'meses' => array_map(fn ($m): array => [
                'start' => $m->toDateString(),
                'label' => ucfirst($m->settings(['locale' => 'es'])->translatedFormat('F')),
            ], $meses),
            'medidas' => $medidas->map(fn (KpiMeasure $m): array => [
                'value' => (string) $m->id,
                'label' => $m->fullName(),
                'unit' => $m->unit->value,
                'summable' => $m->isSummable(),
            ]),
            'medida' => $medida === null ? null : (string) $medida->id,
            'filas' => $medida === null ? [] : $this->rows($medida, $trimestre, $meses, $resolver),
            'historial' => $medida === null ? [] : $this->history($medida),
        ]);
    }

    public function store(Request $request, TargetBook $book): RedirectResponse
    {
        $datos = $request->validate([
            'kpi_measure_id' => ['required', 'integer', 'exists:kpi_measures,id'],
            'reason' => ['nullable', 'string', 'max:500'],
            'cells' => ['required', 'array'],
            'cells.*.scope' => ['required', Rule::enum(TargetScope::class)],
            'cells.*.id' => ['nullable', 'integer'],
            'cells.*.period_type' => ['required', Rule::in([KpiPeriodType::Mes->value, KpiPeriodType::Trimestre->value])],
            'cells.*.period_start' => ['required', 'date'],
            'cells.*.value' => ['nullable', 'numeric', 'min:0'],
        ], [], ['cells.*.value' => 'meta']);

        $cambios = $book->save(
            KpiMeasure::query()->with(['left', 'right'])->whereKey($datos['kpi_measure_id'])->firstOrFail(),
            $datos['cells'],
            $request->user(),
            $datos['reason'] ?? null,
        );

        return $this->backWithToast($cambios === 0 ? 'No había cambios que guardar.' : ($cambios === 1 ? 'Se guardó 1 meta.' : "Se guardaron {$cambios} metas."));
    }

    /**
     * @param  list<CarbonImmutable>  $meses
     * @return list<array<string, mixed>>
     */
    private function rows(KpiMeasure $medida, Period $trimestre, array $meses, TargetResolver $resolver): array
    {
        $alcances = [['scope' => 'general', 'id' => null, 'name' => 'Todo ventas', 'group' => 'General', 'scopeObj' => Scope::general()]];

        foreach (SalesTeam::query()->where('is_active', true)->orderBy('name')->get() as $equipo) {
            $alcances[] = ['scope' => 'equipo', 'id' => $equipo->id, 'name' => $equipo->name, 'group' => 'Equipos', 'scopeObj' => Scope::team($equipo->id)];
        }

        $asesores = SalesAssignment::query()
            ->with('user:id,name', 'team:id,name')
            ->where('role', SalesRole::Asesor->value)
            ->overlapping($trimestre->start->toDateString(), $trimestre->end->toDateString())
            ->orderBy('starts_on')
            ->get()
            ->keyBy('user_id')
            ->sortBy(fn (SalesAssignment $a): string => $a->user->name);

        foreach ($asesores as $tramo) {
            $alcances[] = [
                'scope' => 'persona', 'id' => $tramo->user_id, 'name' => $tramo->user->name,
                'group' => 'Asesores', 'team' => $tramo->team?->name, 'scopeObj' => Scope::person($tramo->user_id),
            ];
        }

        return array_map(function (array $a) use ($medida, $trimestre, $meses, $resolver): array {
            $scope = $a['scopeObj'];
            $delMes = array_map(fn ($m): ?float => $resolver->explicit($medida, $scope, KpiPeriodType::Mes, $m), $meses);
            $sumaMeses = $medida->isSummable() && array_filter($delMes, fn ($v) => $v !== null) !== []
                ? array_sum(array_map(fn ($v) => $v ?? 0, $delMes))
                : null;

            unset($a['scopeObj']);

            return [
                ...$a,
                'key' => $a['scope'].':'.($a['id'] ?? ''),
                'months' => $delMes,
                'quarter' => $resolver->explicit($medida, $scope, KpiPeriodType::Trimestre, $trimestre->start),
                'months_sum' => $sumaMeses,
                'months_sum_label' => $sumaMeses === null ? null : ValueFormatter::format($sumaMeses, $medida->unit),
            ];
        }, $alcances);
    }

    /** @return list<array<string, mixed>> */
    private function history(KpiMeasure $medida): array
    {
        return array_values(KpiTarget::query()
            ->with(['creator:id,name'])
            ->where('kpi_measure_id', $medida->id)
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (KpiTarget $t): array => [
                'id' => $t->id,
                'who' => match ($t->scope) {
                    TargetScope::General => 'Todo ventas',
                    TargetScope::Equipo => SalesTeam::query()->find($t->sales_team_id)->name ?? 'Equipo',
                    TargetScope::Persona => User::query()->find($t->user_id)->name ?? 'Persona',
                },
                'period' => $t->period_type === KpiPeriodType::Trimestre
                    ? 'T'.$t->period_start->quarter.' '.$t->period_start->year
                    : ucfirst($t->period_start->settings(['locale' => 'es'])->translatedFormat('F Y')),
                'value' => ValueFormatter::format((float) $t->value, $medida->unit),
                'reason' => $t->reason,
                'by' => $t->creator?->name,
                'at' => $t->created_at?->toIso8601String(),
                'replaced' => $t->replaced_at !== null,
            ])
            ->all());
    }
}
