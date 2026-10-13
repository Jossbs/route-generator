<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TrustEventType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $visit_id
 * @property TrustEventType $event
 * @property string|null $target
 * @property string|null $advisor_key
 * @property int|null $advisor_id
 * @property int|null $route_id
 * @property int|null $lead_id
 * @property string|null $ip_hash
 * @property array<string, mixed>|null $meta
 * @property CarbonImmutable $created_at
 */
class TrustEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return BelongsTo<LearningRoute, $this> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(LearningRoute::class, 'route_id');
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<User, $this> */
    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    protected function casts(): array
    {
        return [
            'event' => TrustEventType::class,
            'meta' => 'array',
        ];
    }
}
