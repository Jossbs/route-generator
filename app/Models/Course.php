<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CourseLevel;
use App\Enums\RouteLayer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $specialization_id
 * @property int $content_unit_id
 * @property CourseLevel $level
 * @property string|null $short_description
 * @property int|null $lessons_count
 * @property int|null $duration_hours
 * @property float|null $complexity_index
 * @property int|null $complexity_level
 * @property array{c1: int, c2: int, c3: int, c4: int}|null $complexity_criteria
 * @property list<string>|null $allowed_layers
 * @property bool $needs_overlap_review
 * @property bool $needs_duration_review
 * @property string|null $analysis_note
 * @property string|null $outcome_phrase
 * @property string $outcome_phrase_status
 * @property string $delivery_mode
 * @property string $credential_type
 * @property bool $is_active
 */
class Course extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<Specialization, $this> */
    public function specialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class);
    }

    /** @return BelongsTo<ContentUnit, $this> */
    public function contentUnit(): BelongsTo
    {
        return $this->belongsTo(ContentUnit::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Los niveles que este curso absorbe.
     *
     * @return BelongsToMany<Course, $this>
     */
    public function contains(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_containments', 'parent_course_id', 'child_course_id')
            ->withPivot(['containment_type', 'verified_at', 'notes']);
    }

    /**
     * Los paquetes que absorben a este curso.
     *
     * @return BelongsToMany<Course, $this>
     */
    public function containedBy(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_containments', 'child_course_id', 'parent_course_id')
            ->withPivot(['containment_type', 'verified_at', 'notes']);
    }

    /** @return BelongsToMany<Course, $this> */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_prerequisites', 'course_id', 'requires_course_id')
            ->withPivot('strength');
    }

    /** @param Builder<Course> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * ¿Puede este curso aparecer en la capa indicada?
     * `allowed_layers` en null significa todas: la columna enumera excepciones,
     * igual que `specializations.allowed_roles`. Tres micro-cursos quedan solo en
     * `inventario` porque el sitio no publica su duración y sin horas no se pueden
     * ordenar en un mes ni sostener el balanceo del itinerario.
     */
    public function allowsLayer(RouteLayer $layer): bool
    {
        return $this->allowed_layers === null
            || in_array($layer->value, $this->allowed_layers, true);
    }

    /**
     * Los cursos que el generador puede proponer como recomendación.
     *
     * @param  Builder<Course>  $query
     */
    public function scopeRecommendable(Builder $query): void
    {
        $query->active()
            ->whereNotNull('duration_hours')
            ->where(fn (Builder $q) => $q
                ->whereNull('allowed_layers')
                ->orWhereJsonContains('allowed_layers', RouteLayer::Itinerario->value));
    }

    protected function casts(): array
    {
        return [
            'level' => CourseLevel::class,
            'complexity_index' => 'float',
            'complexity_criteria' => 'array',
            'allowed_layers' => 'array',
            'needs_overlap_review' => 'boolean',
            'needs_duration_review' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
