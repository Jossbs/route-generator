<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RouteItemRole;
use App\Enums\RouteLayer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $route_id
 * @property int $course_id
 * @property int $specialization_id
 * @property RouteLayer $layer
 * @property RouteItemRole $role
 * @property int|null $month
 * @property int $position
 * @property bool $counts_toward_total
 */
class RouteItem extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<LearningRoute, $this> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(LearningRoute::class, 'route_id');
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<Specialization, $this> */
    public function specialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class);
    }

    protected function casts(): array
    {
        return [
            'layer' => RouteLayer::class,
            'role' => RouteItemRole::class,
            'counts_toward_total' => 'boolean',
        ];
    }
}
