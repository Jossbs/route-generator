<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Kpis\FormulaOperator;
use App\Enums\Kpis\MeasureAggregation;
use App\Enums\Kpis\MeasureKind;
use App\Enums\Kpis\MeasureUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una medida de un KPI: agregada sobre eventos o fórmula sobre otras dos.
 *
 * @property int $id
 * @property int $kpi_id
 * @property string $slug
 * @property string $name
 * @property MeasureUnit $unit
 * @property MeasureKind $kind
 * @property int|null $sales_event_type_id
 * @property MeasureAggregation|null $aggregation
 * @property string|null $field_key
 * @property list<array{field: string, op: string, value: mixed}> $filters
 * @property FormulaOperator|null $formula_operator
 * @property int|null $left_measure_id
 * @property int|null $right_measure_id
 * @property bool $has_target
 * @property bool $higher_is_better
 * @property int $position
 */
class KpiMeasure extends Model
{
    protected $guarded = [];

    /** Los mismos valores por omisión que la migración, para el modelo recién creado. */
    protected $attributes = ['filters' => '[]', 'has_target' => true, 'higher_is_better' => true, 'position' => 0];

    /** @return BelongsTo<Kpi, $this> */
    public function kpi(): BelongsTo
    {
        return $this->belongsTo(Kpi::class);
    }

    /** @return BelongsTo<SalesEventType, $this> */
    public function eventType(): BelongsTo
    {
        return $this->belongsTo(SalesEventType::class, 'sales_event_type_id');
    }

    /** @return BelongsTo<self, $this> */
    public function left(): BelongsTo
    {
        return $this->belongsTo(self::class, 'left_measure_id');
    }

    /** @return BelongsTo<self, $this> */
    public function right(): BelongsTo
    {
        return $this->belongsTo(self::class, 'right_measure_id');
    }

    /** @return HasMany<KpiTarget, $this> */
    public function targets(): HasMany
    {
        return $this->hasMany(KpiTarget::class);
    }

    /**
     * Si el valor de dos tramos se suma, y por tanto si la meta se reparte por día.
     *
     * Una razón nunca; una diferencia o suma solo si sus dos lados lo son.
     */
    public function isSummable(): bool
    {
        if ($this->kind === MeasureKind::Agregado) {
            return (bool) $this->aggregation?->isSummable();
        }

        if ($this->formula_operator === FormulaOperator::Razon) {
            return false;
        }

        return (bool) $this->left?->isSummable() && (bool) $this->right?->isSummable();
    }

    /** El nombre completo, como se lee fuera de la tarjeta de su KPI. */
    public function fullName(): string
    {
        return $this->kpi->name.' · '.$this->name;
    }

    protected function casts(): array
    {
        return [
            'unit' => MeasureUnit::class,
            'kind' => MeasureKind::class,
            'aggregation' => MeasureAggregation::class,
            'filters' => 'array',
            'formula_operator' => FormulaOperator::class,
            'has_target' => 'boolean',
            'higher_is_better' => 'boolean',
        ];
    }
}
