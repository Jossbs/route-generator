<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\RouteValidation\Data\RuleWaivers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * La ruta curada: una lista ordenada de cursos dirigida a un arquetipo.
 *
 * Es un preset que siembra al motor, no una ruta emitida. Lo que el lead recibe
 * vive en `learning_routes` con su snapshot congelado; editar esta ruta mañana no
 * mueve ningún documento de ayer.
 *
 * @property int $id
 * @property int $funnel_id
 * @property string $name
 * @property int $position
 * @property int|null $archetype_id
 * @property int|null $main_specialization_id
 * @property int $duration_months
 * @property string|null $intro_text
 * @property string|null $closing_text
 * @property int|null $choose_n
 */
class FunnelRoute extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    /** @return BelongsTo<Funnel, $this> */
    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    /** @return BelongsTo<Archetype, $this> */
    public function archetype(): BelongsTo
    {
        return $this->belongsTo(Archetype::class);
    }

    /**
     * La especialidad que abre la ruta. Es la que ocupa el rol de ancla cuando el
     * validador evalúa el preset; el resto acompaña.
     *
     * @return BelongsTo<Specialization, $this>
     */
    public function mainSpecialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class, 'main_specialization_id');
    }

    /** @return HasMany<FunnelRouteCourse, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(FunnelRouteCourse::class)->orderBy('position');
    }

    /** @return BelongsToMany<Course, $this> */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'funnel_route_courses')
            ->withPivot(['position', 'suggested_month', 'kind', 'outcome_phrase_override'])
            ->orderBy('funnel_route_courses.position');
    }

    /**
     * Los cursos en el orden en que se presentarán.
     *
     * @return list<int>
     */
    public function orderedCourseIds(): array
    {
        return array_values($this->items->sortBy('position')->pluck('course_id')->map(intval(...))->all());
    }

    /**
     * Lo que el embudo decidió ignorar, acotado a los cursos de esta ruta.
     * Null cuando no ignoró nada.
     */
    public function ruleWaivers(): ?RuleWaivers
    {
        $codes = $this->funnel?->waivedRuleCodes() ?? [];

        return $codes === [] ? null : new RuleWaivers($codes, $this->orderedCourseIds());
    }

    /**
     * El mes sugerido de cada curso. Sugerido y nunca fecha: los cursos son
     * autogestivos con acceso 24/7, así que la ruta es recomendación de secuencia.
     *
     * @return array<int, int|null>
     */
    public function monthsByCourseId(): array
    {
        return $this->items
            ->mapWithKeys(fn (FunnelRouteCourse $item): array => [
                (int) $item->course_id => $item->suggested_month,
            ])
            ->all();
    }

    /**
     * Los textos de esta ruta que el validador escanea contra `banned_phrases`.
     * El copy es catálogo, no plantilla: si viviera dentro de un Blade no habría
     * nada que escanear, y las restricciones de fraseo serían inverificables.
     *
     * @return list<string>
     */
    public function copyText(): array
    {
        return array_values(array_filter([
            $this->funnel?->headline,
            $this->funnel?->subheadline,
            $this->funnel?->public_name,
            $this->name,
            $this->intro_text,
            $this->closing_text,
        ], fn (?string $text): bool => filled($text)));
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'duration_months' => 'integer',
            'choose_n' => 'integer',
        ];
    }
}
