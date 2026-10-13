<?php

declare(strict_types=1);

namespace App\Services\Kpis;

use App\Enums\Kpis\KpiPeriodType;
use App\Models\Kpi;
use App\Models\KpiMeasure;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Los números de un periodo y un alcance: lo que pinta el tablero, lo que imprime
 * el PDF, lo que resume Telegram y lo que congela el cierre de mes.
 *
 * Una sola fuente para los cuatro, para que nunca digan cosas distintas.
 *
 * Por cada medida:
 * - el avance del alcance contra su meta (`Progress`),
 * - la serie por día del periodo, con lo acumulado y lo esperado acumulado,
 * - el ranking abierto de asesores (decidido el 2026-09-26), cada uno contra su
 *   propia meta, con su racha de días con resultado y su mejor mes.
 */
final class Scoreboard
{
    /** Hasta cuántos días atrás se busca una racha. */
    private const STREAK_LOOKBACK_DAYS = 90;

    /** Cuántos meses atrás se busca la mejor marca. */
    private const BEST_LOOKBACK_MONTHS = 12;

    public function __construct(
        private readonly MeasureEvaluator $evaluator,
        private readonly TargetResolver $targets,
        private readonly SalesRoster $roster,
    ) {}

    /** @return Collection<int, Kpi> */
    public function kpis(): Collection
    {
        return Kpi::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->with(['measures.left.left', 'measures.left.right', 'measures.right.left', 'measures.right.right', 'measures.kpi'])
            ->get();
    }

    /**
     * @param  bool  $detail  sin serie ni rachas: lo que necesitan el resumen y el cierre.
     * @return list<array<string, mixed>>
     */
    public function build(Period $period, Scope $scope, ?CarbonImmutable $today = null, bool $detail = true): array
    {
        $hoy = $today ?? Period::today();
        $asesores = $this->roster->advisors($period->start, $period->end, $scope);

        return array_values($this->kpis()->map(fn (Kpi $kpi): array => [
            'id' => $kpi->id,
            'slug' => $kpi->slug,
            'name' => $kpi->name,
            'description' => $kpi->description,
            'measures' => $kpi->measures->map(
                fn (KpiMeasure $m): array => $this->measure($m, $period, $scope, $hoy, $asesores, $detail),
            )->all(),
        ])->all());
    }

    /**
     * @param  list<array{id: int, name: string, team: string|null}>  $advisors
     * @return array<string, mixed>
     */
    public function measure(KpiMeasure $measure, Period $period, Scope $scope, CarbonImmutable $today, array $advisors, bool $detail = true): array
    {
        $sumable = $measure->isSummable();
        $valor = $this->evaluator->value($measure, $period->start, $period->end, $scope);
        $meta = $this->targets->for($measure, $scope, $period->start, $period->end);
        $avance = Progress::for($valor, $meta, $period, $today, $sumable, $measure->higher_is_better);

        return [
            'id' => $measure->id,
            'slug' => $measure->slug,
            'name' => $measure->name,
            'unit' => $measure->unit->value,
            'summable' => $sumable,
            'higher_is_better' => $measure->higher_is_better,
            'has_target' => $measure->has_target,
            'progress' => $avance->toArray($measure->unit),
            'series' => $detail && $period->type !== KpiPeriodType::Dia
                ? $this->series($measure, $period, $scope, $meta, $today)
                : [],
            'ranking' => $this->ranking($measure, $period, $today, $advisors, $detail),
        ];
    }

    /**
     * Un punto por día: lo del día, lo acumulado y lo esperado acumulado. Los días
     * que no han llegado van sin valor, para que la línea no caiga a cero.
     *
     * @return list<array{date: string, value: float|null, cumulative: float|null, expected: float|null}>
     */
    private function series(KpiMeasure $measure, Period $period, Scope $scope, ?float $target, CarbonImmutable $today): array
    {
        $porDia = $this->evaluator->grouped($measure, $period->start, $period->end, $scope, ['day']);
        $sumable = $measure->isSummable();
        $acumulado = 0.0;
        $puntos = [];

        foreach ($period->eachDay() as $i => $dia) {
            $llave = $dia->toDateString();
            $futuro = $dia->gt($today);
            $valor = $porDia[$llave] ?? ($sumable ? 0.0 : null);
            $acumulado += $valor ?? 0.0;

            $puntos[] = [
                'date' => $llave,
                'value' => $futuro ? null : $valor,
                'cumulative' => $futuro || ! $sumable ? null : $acumulado,
                'expected' => $sumable && $target !== null ? $target * ($i + 1) / $period->days() : null,
            ];
        }

        return $puntos;
    }

    /**
     * @param  list<array{id: int, name: string, team: string|null}>  $advisors
     * @return list<array<string, mixed>>
     */
    private function ranking(KpiMeasure $measure, Period $period, CarbonImmutable $today, array $advisors, bool $detail): array
    {
        if ($advisors === []) {
            return [];
        }

        $general = Scope::general();
        $porPersona = $this->evaluator->grouped($measure, $period->start, $period->end, $general, ['user']);
        $sumable = $measure->isSummable();
        $rachas = $detail && $sumable ? $this->streaks($measure, $period, $today) : [];
        $mejores = $detail ? $this->bests($measure, $period, $today) : [];
        $mesActual = $this->evaluator->grouped($measure, $period->end->startOfMonth(), $period->end->endOfMonth()->startOfDay(), $general, ['user']);

        $filas = array_map(function (array $asesor) use ($measure, $period, $today, $porPersona, $sumable, $rachas, $mejores, $mesActual): array {
            $valor = $porPersona[(string) $asesor['id']] ?? ($sumable ? 0.0 : null);
            $meta = $this->targets->for($measure, Scope::person($asesor['id']), $period->start, $period->end);
            $avance = Progress::for($valor, $meta, $period, $today, $sumable, $measure->higher_is_better);
            $mejor = $mejores[$asesor['id']] ?? null;
            $delMes = $mesActual[(string) $asesor['id']] ?? null;

            return [
                ...$asesor,
                'value' => $valor,
                'value_label' => ValueFormatter::format($valor, $measure->unit),
                'target' => $meta,
                'target_label' => ValueFormatter::format($meta, $measure->unit),
                'percent' => $avance->percent,
                'status' => $avance->status,
                'milestone' => $avance->milestone,
                'streak' => $rachas[$asesor['id']] ?? 0,
                'best_month' => $mejor,
                'best_month_label' => $mejor === null ? null : ValueFormatter::format($mejor['value'], $measure->unit),
                // El mes del periodo ya igualó o superó su mejor mes anterior.
                'is_personal_best' => $mejor !== null && $delMes !== null && $delMes > 0
                    && ($measure->higher_is_better ? $delMes >= $mejor['value'] : $delMes <= $mejor['value']),
            ];
        }, $advisors);

        usort($filas, function (array $a, array $b) use ($measure): int {
            $orden = ($b['value'] ?? -INF) <=> ($a['value'] ?? -INF);

            return ($measure->higher_is_better ? $orden : -$orden) ?: strcmp($a['name'], $b['name']);
        });

        $lugar = 0;
        $anterior = null;

        foreach ($filas as $i => &$fila) {
            // Empates comparten lugar: dos asesores con 12 ventas son los dos segundos.
            if ($fila['value'] !== $anterior) {
                $lugar = $i + 1;
                $anterior = $fila['value'];
            }
            $fila['position'] = $lugar;
        }

        return $filas;
    }

    /**
     * Los días seguidos con resultado de cada asesor, contados hacia atrás desde
     * el último día del periodo que ya pasó. Si hoy todavía no vende, la racha de
     * ayer sigue viva: el día no ha terminado.
     *
     * @return array<int, int>
     */
    private function streaks(KpiMeasure $measure, Period $period, CarbonImmutable $today): array
    {
        $hasta = $period->end->min($today);
        $desde = $hasta->subDays(self::STREAK_LOOKBACK_DAYS);
        $valores = $this->evaluator->grouped($measure, $desde, $hasta, Scope::general(), ['user', 'day']);
        $dias = [];

        foreach ($valores as $llave => $valor) {
            [$usuario, $dia] = explode('|', (string) $llave);

            if (($valor ?? 0) > 0) {
                $dias[(int) $usuario][$dia] = true;
            }
        }

        $rachas = [];

        foreach ($dias as $usuario => $conResultado) {
            $dia = isset($conResultado[$hasta->toDateString()]) ? $hasta : $hasta->subDay();
            $racha = 0;

            while (isset($conResultado[$dia->toDateString()])) {
                $racha++;
                $dia = $dia->subDay();
            }

            $rachas[$usuario] = $racha;
        }

        return $rachas;
    }

    /**
     * El mejor mes de cada asesor antes del mes del periodo.
     *
     * @return array<int, array{month: string, value: float}>
     */
    private function bests(KpiMeasure $measure, Period $period, CarbonImmutable $today): array
    {
        $mes = $period->end->min($today)->startOfMonth();
        $valores = $this->evaluator->grouped(
            $measure,
            $mes->subMonths(self::BEST_LOOKBACK_MONTHS),
            $mes->subDay(),
            Scope::general(),
            ['user', 'month'],
        );
        $mejores = [];

        foreach ($valores as $llave => $valor) {
            [$usuario, $inicio] = explode('|', (string) $llave);

            if ($valor === null || $valor <= 0) {
                continue;
            }

            $actual = $mejores[(int) $usuario]['value'] ?? null;
            $mejora = $actual === null || ($measure->higher_is_better ? $valor > $actual : $valor < $actual);

            if ($mejora) {
                $mejores[(int) $usuario] = ['month' => $inicio, 'value' => $valor];
            }
        }

        return $mejores;
    }
}
