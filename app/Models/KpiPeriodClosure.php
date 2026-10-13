<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un mes cerrado, con los números que se reportaron al cerrarlo.
 *
 * @property int $id
 * @property CarbonImmutable $period_start
 * @property array<string, mixed> $snapshot
 * @property int|null $closed_by
 * @property CarbonImmutable $closed_at
 */
class KpiPeriodClosure extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<User, $this> */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'snapshot' => 'array',
            'closed_at' => 'datetime',
        ];
    }
}
