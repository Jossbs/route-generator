<?php

declare(strict_types=1);

namespace App\Services\RouteComposition\Composers;

use App\Enums\RouteItemRole;
use App\Models\Specialization;
use App\Services\RouteComposition\Contracts\RouteComposer;
use App\Services\RouteComposition\Data\CompositionPolicy;
use App\Services\RouteComposition\Data\CompositionRequest;
use App\Services\RouteComposition\Exceptions\CompositionException;
use App\Services\RouteComposition\Support\ComplementaryPicker;
use App\Services\RouteComposition\Support\CompositionCatalog;
use App\Services\RouteComposition\Support\CourseRanker;
use App\Services\RouteComposition\Support\DraftAssembler;
use App\Services\RouteComposition\Support\ExclusionGuard;
use App\Services\RouteComposition\Support\LeadSignals;
use App\Services\RouteValidation\Data\RouteDraft;
use Database\Seeders\DocumentTypeSeeder;

/**
 * El gancho: la especialización que el lead pidió, completa, más las dos
 * complementarias de mayor afinidad con sus cursos más representativos.
 *
 * Es el documento que demuestra trato personalizado antes de la venta, así que
 * la ancla va entera —el lead debe ver que lo que pidió está cubierto de punta a
 * punta— y las complementarias son la muestra de lo que no sabía que venía dentro.
 */
final class FocusedRouteComposer implements RouteComposer
{
    public static function documentType(): string
    {
        return DocumentTypeSeeder::ENFOCADA;
    }

    public function compose(CompositionRequest $request): RouteDraft
    {
        $policy = CompositionPolicy::for($request->documentType);
        $catalog = new CompositionCatalog($request->archetype);
        $guard = new ExclusionGuard($request->documentType->slug);
        $ranker = new CourseRanker($policy, $request->archetype);
        $assembler = new DraftAssembler;

        $anchor = $this->resolveAnchor($request->anchorSpecialization, $catalog);

        // La ancla completa: todos sus cursos recomendables, en orden pedagógico y
        // esquivando los traslapes. De once cursos Lean pueden quedar diez si uno
        // choca con otro; el validador lo confirmaría después, pero proponerlo
        // rechazado sería hacerle perder el viaje al asesor.
        $anchorCourses = $ranker->select(
            courses: $catalog->recommendableIn($anchor, expandPackages: true),
            limit: PHP_INT_MAX,
            guard: $guard,
        );

        $assembler->block(
            $anchor,
            $anchorCourses,
            RouteItemRole::Ancla,
            justification: 'Por la que preguntó. Va en el orden en que se aprende.',
        );

        $chosen = array_map(fn ($course): int => $course->id, $anchorCourses);

        $signals = LeadSignals::of($request->lead);
        $complementaries = $this->complementaries($anchor, $catalog, $policy, $signals);
        $pending = count($complementaries);

        foreach ($complementaries as [$complementary, $justification]) {
            // El rango de 8 a 14 programas es una expectativa, no una restricción
            // sembrada: cuando la ancla ya lo llena —Lean son once cursos, Soft
            // Skills trece— la complementaria se aprieta a su mínimo antes que
            // desaparecer. Un documento sin complementarias deja de mostrarle al
            // lead lo que no sabía que venía dentro, que es de lo que vive el gancho.
            $room = $policy->maxPrograms() - count($chosen);

            // Y al revés: con un ancla de dos cursos —Normas ISO, Análisis de
            // Datos— las complementarias son las que tienen que levantar el
            // documento hasta el mínimo, o el lead recibe media hoja.
            $share = $pending > 0
                ? (int) ceil(max(0, $policy->minPrograms() - count($chosen)) / $pending)
                : 0;

            $courses = $ranker->select(
                courses: $catalog->recommendableIn($complementary),
                limit: max(
                    $policy->minCoursesPerComplementary(),
                    min(max($policy->coursesPerComplementary(), $share), $room),
                ),
                guard: $guard,
                alreadyChosen: $chosen,
            );

            $pending--;

            // Una complementaria que no alcanza el mínimo se lee como relleno junto
            // a una ancla de ocho cursos. Mejor omitirla que sostenerla a medias.
            if (count($courses) < $policy->minCoursesPerComplementary()) {
                continue;
            }

            $assembler->block(
                $complementary,
                $courses,
                RouteItemRole::Complementaria,
                justification: $justification,
            );

            $chosen = [...$chosen, ...array_map(fn ($course): int => $course->id, $courses)];
        }

        return new RouteDraft(
            documentTypeSlug: $request->documentType->slug,
            items: $assembler->items(),
            anchorSpecializationId: $anchor->id,
            headings: $assembler->headings(),
            copyText: [],
            membership: $request->membershipFields(),
            declaredCourseCount: $assembler->courseCount(),
            declaredContentUnitCount: $assembler->contentUnitCount(),
        );
    }

    /**
     * La especialización que pidió el lead, si su ficha admite el rol de ancla.
     *
     * Cadena de Suministros tiene un solo curso y no puede sostener el bloque
     * principal. No se sustituye en silencio: el asesor tiene que enterarse, y el
     * lead que pidió inventarios merece una ruta pensada, no una traducción
     * automática a otra especialidad.
     */
    private function resolveAnchor(Specialization $requested, CompositionCatalog $catalog): Specialization
    {
        if (! $requested->allowsRole('ancla')) {
            throw CompositionException::anchorNotAllowed($requested);
        }

        if ($catalog->recommendableIn($requested)->isEmpty()) {
            throw CompositionException::anchorWithoutCourses($requested);
        }

        return $requested;
    }

    /**
     * Las dos complementarias, elegidas por lo que el asesor capturó del lead.
     *
     * El criterio vive en ComplementaryPicker porque no es solo de este
     * compositor: el mismo orden de preferencia decide con qué se completa un
     * embudo que se quedó corto de recomendados.
     *
     * @return list<array{Specialization, string}>
     */
    private function complementaries(
        Specialization $anchor,
        CompositionCatalog $catalog,
        CompositionPolicy $policy,
        LeadSignals $signals,
    ): array {
        return (new ComplementaryPicker)->for(
            $signals,
            $catalog,
            excludeIds: [$anchor->id],
            limit: $policy->complementaryCount(),
        );
    }
}
