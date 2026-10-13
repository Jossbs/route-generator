<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FunnelCourseKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un renglón de la ruta curada.
 *
 * `suggested_month` es sugerencia, nunca fecha: los cursos son autogestivos y el
 * documento se redacta como recomendación de secuencia.
 *
 * @property int $id
 * @property int $funnel_route_id
 * @property int $course_id
 * @property int $position
 * @property int|null $suggested_month
 * @property FunnelCourseKind $kind
 * @property string|null $outcome_phrase_override
 */
class FunnelRouteCourse extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<FunnelRoute, $this> */
    public function funnelRoute(): BelongsTo
    {
        return $this->belongsTo(FunnelRoute::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    protected function casts(): array
    {
        return [
            'kind' => FunnelCourseKind::class,
            'position' => 'integer',
            'suggested_month' => 'integer',
        ];
    }
}
