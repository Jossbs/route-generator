<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\Kpis\FormulaOperator;
use App\Enums\Kpis\KpiPeriodType;
use App\Enums\Kpis\MeasureAggregation;
use App\Enums\Kpis\MeasureKind;
use App\Enums\Kpis\MeasureUnit;
use App\Enums\Kpis\SalesEventSource;
use App\Enums\Kpis\SalesEventStatus;
use App\Enums\Kpis\SalesFieldType;
use App\Enums\Kpis\SalesRole;
use App\Enums\Kpis\TargetScope;
use App\Models\Kpi;
use App\Models\KpiMeasure;
use App\Models\KpiTarget;
use App\Models\SalesAssignment;
use App\Models\SalesEvent;
use App\Models\SalesEventType;
use App\Models\SalesTeam;
use App\Models\User;

/**
 * Datos mínimos para probar el módulo de KPIs sin sembrar nada: un tipo «venta»
 * con plan, importe y canal, y atajos para medidas, eventos, metas y equipos.
 */
final class KpiFactory
{
    public static function saleType(): SalesEventType
    {
        $tipo = SalesEventType::create(['slug' => 'venta', 'name' => 'Venta']);

        $tipo->fields()->createMany([
            ['key' => 'plan', 'label' => 'Plan', 'data_type' => SalesFieldType::Opcion, 'is_required' => true, 'position' => 0,
                'options' => [['value' => 'expert_anual', 'label' => 'Expert Anual'], ['value' => 'expert_mensual', 'label' => 'Expert Mensual'], ['value' => 'duo', 'label' => 'Duo']]],
            ['key' => 'importe', 'label' => 'Importe', 'data_type' => SalesFieldType::Dinero, 'is_required' => true, 'position' => 1, 'options' => []],
            ['key' => 'cantidad', 'label' => 'Cantidad', 'data_type' => SalesFieldType::Numero, 'is_required' => false, 'position' => 2, 'options' => []],
        ]);

        return $tipo->load('fields');
    }

    public static function leadType(): SalesEventType
    {
        $tipo = SalesEventType::create(['slug' => 'lead', 'name' => 'Lead atendido']);
        $tipo->fields()->create(['key' => 'canal', 'label' => 'Canal', 'data_type' => SalesFieldType::Texto, 'position' => 0, 'options' => []]);

        return $tipo->load('fields');
    }

    public static function kpi(string $name = 'Ventas'): Kpi
    {
        return Kpi::create(['slug' => str($name)->slug()->toString(), 'name' => $name]);
    }

    /** @param  array<string, mixed>  $attrs */
    public static function aggregate(Kpi $kpi, SalesEventType $type, MeasureAggregation $aggregation, ?string $field = null, array $attrs = []): KpiMeasure
    {
        return $kpi->measures()->create([
            'slug' => $attrs['slug'] ?? $aggregation->value.($field ? '-'.$field : ''),
            'name' => $attrs['name'] ?? ucfirst($aggregation->value),
            'unit' => $attrs['unit'] ?? ($field === 'importe' ? MeasureUnit::Dinero : MeasureUnit::Piezas),
            'kind' => MeasureKind::Agregado,
            'sales_event_type_id' => $type->id,
            'aggregation' => $aggregation,
            'field_key' => $field,
            'filters' => $attrs['filters'] ?? [],
            'higher_is_better' => $attrs['higher_is_better'] ?? true,
            'has_target' => $attrs['has_target'] ?? true,
        ])->load('kpi');
    }

    public static function formula(Kpi $kpi, FormulaOperator $op, KpiMeasure $left, KpiMeasure $right, MeasureUnit $unit = MeasureUnit::Porcentaje): KpiMeasure
    {
        return $kpi->measures()->create([
            'slug' => 'formula-'.$op->value.'-'.$left->id.'-'.$right->id,
            'name' => 'Fórmula',
            'unit' => $unit,
            'kind' => MeasureKind::Formula,
            'formula_operator' => $op,
            'left_measure_id' => $left->id,
            'right_measure_id' => $right->id,
        ])->load(['kpi', 'left', 'right']);
    }

    /** @param  array<string, mixed>  $values */
    public static function event(SalesEventType $type, User $user, string $date, array $values = [], array $attrs = []): SalesEvent
    {
        return SalesEvent::create([
            'sales_event_type_id' => $type->id,
            'user_id' => $user->id,
            'occurred_on' => $date,
            'values' => $values,
            'source' => $attrs['source'] ?? SalesEventSource::Manual,
            'external_id' => $attrs['external_id'] ?? null,
            'status' => $attrs['status'] ?? SalesEventStatus::Confirmado,
            'voided_at' => $attrs['voided_at'] ?? null,
        ]);
    }

    /** Una venta con importe; el plan anual por omisión. */
    public static function sale(SalesEventType $type, User $user, string $date, float $amount = 4199, string $plan = 'expert_anual', array $attrs = []): SalesEvent
    {
        return self::event($type, $user, $date, ['plan' => $plan, 'importe' => $amount], $attrs);
    }

    public static function team(string $name): SalesTeam
    {
        return SalesTeam::create(['name' => $name]);
    }

    public static function assign(User $user, ?SalesTeam $team, string $from, ?string $to = null, SalesRole $role = SalesRole::Asesor): SalesAssignment
    {
        return SalesAssignment::create([
            'user_id' => $user->id,
            'sales_team_id' => $team?->id,
            'role' => $role,
            'starts_on' => $from,
            'ends_on' => $to,
        ]);
    }

    public static function target(KpiMeasure $measure, KpiPeriodType $type, string $start, float $value, TargetScope $scope = TargetScope::General, ?int $id = null): KpiTarget
    {
        return KpiTarget::create([
            'kpi_measure_id' => $measure->id,
            'scope' => $scope,
            'sales_team_id' => $scope === TargetScope::Equipo ? $id : null,
            'user_id' => $scope === TargetScope::Persona ? $id : null,
            'period_type' => $type,
            'period_start' => $start,
            'value' => $value,
        ]);
    }
}
