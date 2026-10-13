<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UsageEventType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $route_id
 * @property int|null $advisor_id
 * @property UsageEventType $event
 * @property string|null $ip_hash
 * @property array<string, mixed>|null $meta
 * @property CarbonImmutable $created_at
 */
class UsageEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return BelongsTo<LearningRoute, $this> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(LearningRoute::class, 'route_id');
    }

    /** @return BelongsTo<User, $this> */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    protected function casts(): array
    {
        return [
            'event' => UsageEventType::class,
            'meta' => 'array',
        ];
    }
}
