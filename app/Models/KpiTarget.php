<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Kpis\KpiPeriodType;
use App\Enums\Kpis\TargetScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una versión de una meta. La vigente es la que no tiene `replaced_at`.
 *
 * @property int $id
 * @property int $kpi_measure_id
 * @property TargetScope $scope
 * @property int|null $sales_team_id
 * @property int|null $user_id
 * @property KpiPeriodType $period_type
 * @property CarbonImmutable $period_start
 * @property string $value
 * @property string|null $reason
 * @property int|null $created_by
 * @property CarbonImmutable|null $replaced_at
 */
class KpiTarget extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<KpiMeasure, $this> */
    public function measure(): BelongsTo
    {
        return $this->belongsTo(KpiMeasure::class, 'kpi_measure_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param  Builder<self>  $query */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('replaced_at');
    }

    protected function casts(): array
    {
        return [
            'scope' => TargetScope::class,
            'period_type' => KpiPeriodType::class,
            'period_start' => 'date',
            'value' => 'decimal:2',
            'replaced_at' => 'datetime',
        ];
    }
}
