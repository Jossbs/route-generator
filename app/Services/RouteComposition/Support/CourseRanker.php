<?php

declare(strict_types=1);

namespace App\Services\RouteComposition\Support;

use App\Models\Archetype;
use App\Models\Course;
use App\Services\Catalog\ComplexityRubric;
use App\Services\RouteComposition\Data\CompositionPolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Decide qué cursos de una especialización representan mejor lo que el lead pidió.
 *
 * El peso combina dos señales: la afinidad del arquetipo con las etiquetas del
 * curso y su nivel de complejidad. Mientras los ocho arquetipos del Excel no
 * lleguen, la afinidad casi siempre vale cero y manda la complejidad, que es la
 * lectura razonable: sin saber quién es el lead, los cursos que definen a una
 * especialidad son los que exigen más.
 *
 * Elegir por peso y presentar por secuencia son cosas distintas: `select()`
 * escoge los más representativos y `sequence()` los devuelve en orden pedagógico,
 * que es lo que LEVEL_SEQUENCE exige del documento.
 */
final class CourseRanker
{
    /** @var array<int, float> Etiqueta => peso de afinidad del arquetipo. */
    private array $tagAffinity;

    public function __construct(
        private readonly CompositionPolicy $policy,
        ?Archetype $archetype = null,
    ) {
        // Se lee la tabla de afinidad directa en vez de hidratar los modelos Tag:
        // de la etiqueta solo interesa su peso, y así el mapa llega ya tipado.
        $this->tagAffinity = $archetype === null
            ? []
            : DB::table('archetype_tag_affinity')
                ->where('archetype_id', $archetype->id)
                ->pluck('weight', 'tag_id')
                ->map(floatval(...))
                ->all();
    }

    /**
     * Los mejores `$limit` cursos, ya en orden de presentación.
     *
     * @param  Collection<int, Course>  $courses
     * @param  list<int>  $alreadyChosen
     * @return list<Course>
     */
    public function select(Collection $courses, int $limit, ExclusionGuard $guard, array $alreadyChosen = []): array
    {
        $chosen = $alreadyChosen;
        $picked = [];

        foreach ($this->byWeight($courses) as $course) {
            if (count($picked) >= $limit) {
                break;
            }

            if (! $guard->admits($course->id, $chosen)) {
                continue;
            }

            $picked[] = $course;
            $chosen[] = $course->id;
        }

        return $this->sequence(collect($picked));
    }

    /**
     * De más a menos representativo.
     *
     * @param  Collection<int, Course>  $courses
     * @return list<Course>
     */
    public function byWeight(Collection $courses): array
    {
        // Entre cursos del mismo peso gana el más accesible. Importa donde se
        // decide qué sobrevive de un grupo de traslape: Green Belt y Black Belt
        // pesan igual, y recomendar Black Belt sin Green Belt antes contradice la
        // secuencia que el propio documento promete.
        //
        // El slug cierra el desempate para que la misma solicitud produzca el mismo
        // documento en dos corridas, en vez del orden que devuelva PostgreSQL.
        return array_values($courses
            ->sortBy(fn (Course $course): array => [
                -$this->weight($course),
                $course->complexity_index ?? 0.0,
                $course->slug,
            ])
            ->all());
    }

    /**
     * Orden de presentación: primero lo que funda, después lo que se apoya en ello.
     * Es el orden que LEVEL_SEQUENCE verifica sobre el documento terminado.
     *
     * @param  Collection<int, Course>  $courses
     * @return list<Course>
     */
    public function sequence(Collection $courses): array
    {
        return array_values($courses
            ->sortBy(fn (Course $course): array => [
                $course->complexity_level ?? ComplexityRubric::MAX_LEVEL,
                $course->complexity_index ?? 0.0,
                $course->duration_hours ?? 0,
                $course->slug,
            ])
            ->all());
    }

    /** Peso normalizado a 0–1, para que las dos señales sean comparables. */
    private function weight(Course $course): float
    {
        $affinity = 0.0;

        foreach ($course->tags as $tag) {
            $affinity = max($affinity, $this->tagAffinity[$tag->id] ?? 0.0);
        }

        $complexity = ($course->complexity_level ?? ComplexityRubric::MIN_LEVEL) / ComplexityRubric::MAX_LEVEL;

        return $this->policy->affinityWeight() * $affinity
            + $this->policy->complexityWeight() * $complexity;
    }
}
