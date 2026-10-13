<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Kpis\SalesRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un tramo de la vida de alguien en ventas: en qué equipo y con qué papel, de
 * qué fecha a qué fecha.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $sales_team_id
 * @property SalesRole $role
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable|null $ends_on
 */
class SalesAssignment extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<SalesTeam, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(SalesTeam::class, 'sales_team_id');
    }

    /**
     * Los tramos que tocan algún día entre las dos fechas.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOverlapping(Builder $query, string $from, string $to): void
    {
        $query->where('starts_on', '<=', $to)
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $from));
    }

    protected function casts(): array
    {
        return [
            'role' => SalesRole::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
