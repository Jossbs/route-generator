<?php

declare(strict_types=1);

namespace App\Services\RouteComposition\Support;

use App\Models\Course;
use App\Models\DocumentType;
use App\Models\Lead;
use App\Models\Specialization;
use App\Services\RouteComposition\Data\CompositionPolicy;

/**
 * Los cursos que el motor recomienda encima de la ruta principal.
 *
 * El documento tiene dos partes que no se leen igual. Arriba va la ruta
 * principal: **todos** los cursos del embudo por el que el lead preguntó, en el
 * orden en que Eje Cursos los curó y sin importar de qué especialidad sea cada
 * uno. La campaña los eligió, así que ninguno se toca. Abajo van los recomendados,
 * que son la muestra de lo que el lead no sabía que venía dentro de su membresía.
 *
 * Se proponen tres cursos de refuerzo según la ocupación y el objetivo del lead.
 * El asesor puede sustituirlos, retirarlos o agregar más durante la revisión.
 *
 * Los del embudo no cuentan aquí aunque sean de otra especialidad. Antes sí
 * contaban, y un embudo con seis cursos de tres ramas salía sin una sola
 * recomendación propia: el lead recibía la campaña tal cual, sin nada que
 * demostrara que alguien miró su caso.
 */
final class RecommendedTopUp
{
    /** Cuántos propone el motor; no limita la selección del asesor. */
    public const OBJETIVO = 3;

    /** Menos de esto no es una sección, es un renglón suelto. */
    public const MINIMO = 3;

    /** Cuántos se toman de una misma especialización antes de pasar a la siguiente. */
    private const POR_ESPECIALIDAD = 2;

    /**
     * Los cursos que hay que sumar, cada uno con su porqué.
     *
     * @param  list<int>  $courseIds  Lo que trae el embudo, en su orden.
     * @return array<int, string> Curso => justificación, en el orden en que se agregan.
     */
    public function for(array $courseIds, ?Specialization $anchor, Lead $lead, DocumentType $documentType): array
    {
        $faltan = self::OBJETIVO;

        $catalog = new CompositionCatalog($lead->archetype);
        $guard = new ExclusionGuard($documentType->slug);
        $ranker = new CourseRanker(CompositionPolicy::for($documentType), $lead->archetype);

        $excluir = array_values(array_filter([$anchor?->id, ...$this->specializationIds($courseIds)]));
        $chosen = $courseIds;
        $sumados = [];

        $candidatas = (new ComplementaryPicker)->for(
            LeadSignals::of($lead),
            $catalog,
            excludeIds: $excluir,
            // De sobra: cada candidata puede quedarse sin cursos que el guard
            // admita, y quedarse corto aquí es volver a quedarse corto abajo.
            limit: PHP_INT_MAX,
        );

        foreach ($candidatas as [$especialidad, $justificacion]) {
            if ($faltan <= 0) {
                break;
            }

            $cursos = $ranker->select(
                courses: $catalog->recommendableIn($especialidad),
                limit: min($faltan, self::POR_ESPECIALIDAD),
                guard: $guard,
                alreadyChosen: $chosen,
            );

            foreach ($cursos as $curso) {
                $sumados[(int) $curso->id] = $justificacion;
                $chosen[] = (int) $curso->id;
                $faltan--;
            }
        }

        return $sumados;
    }

    /**
     * Las especializaciones que el embudo ya trae.
     *
     * Se excluyen para que el relleno abra bloques nuevos en vez de engordar los
     * que Eje Cursos ya curó: si la campaña puso dos cursos de Soft Skills, puso
     * los que quería, y sumarle un tercero por nuestra cuenta le cambia el
     * argumento a la promoción.
     *
     * @param  list<int>  $courseIds
     * @return list<int>
     */
    private function specializationIds(array $courseIds): array
    {
        return array_values(Course::query()
            ->whereIn('id', $courseIds)
            ->distinct()
            ->pluck('specialization_id')
            ->map(intval(...))
            ->all());
    }
}
