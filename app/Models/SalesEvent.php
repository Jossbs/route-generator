<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Kpis\SalesEventSource;
use App\Enums\Kpis\SalesEventStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un hecho de ventas. Ver la migración para por qué nada se borra.
 *
 * @property int $id
 * @property int $sales_event_type_id
 * @property int $user_id
 * @property CarbonImmutable $occurred_on
 * @property array<string, mixed> $values
 * @property string|null $notes
 * @property SalesEventSource $source
 * @property string|null $external_id
 * @property SalesEventStatus $status
 * @property int|null $created_by
 * @property int|null $confirmed_by
 * @property CarbonImmutable|null $confirmed_at
 * @property bool $is_adjustment
 * @property string|null $adjustment_reason
 * @property CarbonImmutable|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 */
class SalesEvent extends Model
{
    protected $guarded = [];

    /** Los mismos valores por omisión que la migración, para el modelo recién creado. */
    protected $attributes = ['values' => '{}', 'source' => 'manual', 'status' => 'confirmado', 'is_adjustment' => false];

    /** @return BelongsTo<SalesEventType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(SalesEventType::class, 'sales_event_type_id');
    }

    /** @return BelongsTo<User, $this> */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Lo que entra a los cálculos: confirmado y sin anular.
     *
     * @param  Builder<self>  $query
     */
    public function scopeCounting(Builder $query): void
    {
        $query->where('status', SalesEventStatus::Confirmado->value)->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'values' => 'array',
            'source' => SalesEventSource::class,
            'status' => SalesEventStatus::class,
            'confirmed_at' => 'datetime',
            'is_adjustment' => 'boolean',
            'voided_at' => 'datetime',
        ];
    }
}
