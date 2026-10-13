<?php

declare(strict_types=1);

namespace App\Services\Kpis;

use App\Enums\Kpis\KpiPeriodType;
use App\Enums\Kpis\TargetScope;
use App\Models\KpiMeasure;
use App\Models\KpiTarget;
use Carbon\CarbonImmutable;

/**
 * La meta de cualquier tramo, a partir de las que se capturan por mes y trimestre.
 *
 * Las metas no se reparten parejo entre meses —10, 20 y 30 hacen un trimestre de
 * 60— pero dentro del mes sí se reparten por día natural, porque ventas trabaja
 * los siete (decidido el 2026-09-26). De ahí salen la semana y el día, y también
 * «lo esperado a hoy».
 *
 * Por mes manda la meta del mes. Si el mes no tiene y el trimestre sí, el mes
 * toma la parte del trimestre que le toca por días. Para el trimestre completo
 * manda la meta del trimestre si se capturó; si no, la suma de sus meses.
 *
 * Las medidas que no se suman —un ticket promedio, una conversión— no se reparten:
 * su meta es un nivel, y vale la del mes (o, sin ella, la del trimestre).
 *
 * Solo cuenta la versión vigente de cada meta.
 */
final class TargetResolver
{
    /** @var array<string, array<string, float>> */
    private array $cache = [];

    public function for(KpiMeasure $measure, Scope $scope, CarbonImmutable $from, CarbonImmutable $to): ?float
    {
        if ($measure->has_target === false) {
            return null;
        }

        $trimestre = Period::containing(KpiPeriodType::Trimestre, $from);
        $esTrimestreCompleto = $from->equalTo($trimestre->start) && $to->equalTo($trimestre->end);

        if (! $measure->isSummable()) {
            $mes = $this->explicit($measure, $scope, KpiPeriodType::Mes, $to->startOfMonth());
            $delTrimestre = $this->explicit($measure, $scope, KpiPeriodType::Trimestre, $to->firstOfQuarter());

            return $esTrimestreCompleto ? ($delTrimestre ?? $mes) : ($mes ?? $delTrimestre);
        }

        if ($esTrimestreCompleto) {
            $delTrimestre = $this->explicit($measure, $scope, KpiPeriodType::Trimestre, $trimestre->start);

            if ($delTrimestre !== null) {
                return $delTrimestre;
            }
        }

        $total = null;

        for ($mes = $from->startOfMonth(); $mes->lte($to); $mes = $mes->addMonth()) {
            $metaDelMes = $this->month($measure, $scope, $mes);

            if ($metaDelMes === null) {
                continue;
            }

            $finDelMes = $mes->endOfMonth()->startOfDay();
            $desde = $from->max($mes);
            $hasta = $to->min($finDelMes);
            $dias = (int) $desde->diffInDays($hasta) + 1;

            $total = ($total ?? 0.0) + $metaDelMes * $dias / $mes->daysInMonth;
        }

        return $total;
    }

    /** La meta del mes: la suya, o la parte del trimestre que le toca por días. */
    public function month(KpiMeasure $measure, Scope $scope, CarbonImmutable $monthStart): ?float
    {
        $propia = $this->explicit($measure, $scope, KpiPeriodType::Mes, $monthStart);

        if ($propia !== null) {
            return $propia;
        }

        $trimestre = Period::containing(KpiPeriodType::Trimestre, $monthStart);
        $delTrimestre = $this->explicit($measure, $scope, KpiPeriodType::Trimestre, $trimestre->start);

        return $delTrimestre === null ? null : $delTrimestre * $monthStart->daysInMonth / $trimestre->days();
    }

    /** La meta capturada tal cual para ese periodo, sin derivar nada. */
    public function explicit(KpiMeasure $measure, Scope $scope, KpiPeriodType $type, CarbonImmutable $periodStart): ?float
    {
        return $this->targets($measure, $scope)[$type->value.'|'.$periodStart->toDateString()] ?? null;
    }

    /** @return array<string, float> */
    private function targets(KpiMeasure $measure, Scope $scope): array
    {
        $llave = $measure->id.'|'.$scope->key();

        return $this->cache[$llave] ??= KpiTarget::query()
            ->current()
            ->where('kpi_measure_id', $measure->id)
            ->where('scope', $scope->type->value)
            ->when($scope->type === TargetScope::Equipo, fn ($q) => $q->where('sales_team_id', $scope->id))
            ->when($scope->type === TargetScope::Persona, fn ($q) => $q->where('user_id', $scope->id))
            ->get()
            ->mapWithKeys(fn (KpiTarget $t): array => [
                $t->period_type->value.'|'.$t->period_start->toDateString() => (float) $t->value,
            ])
            ->all();
    }

    /** Olvida lo leído: después de guardar metas en la misma petición. */
    public function forget(): void
    {
        $this->cache = [];
    }
}
