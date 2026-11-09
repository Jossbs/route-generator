<?php

declare(strict_types=1);

namespace App\Services\RouteComposition\Composers;

use App\Enums\RouteItemRole;
use App\Models\Course;
use App\Models\Specialization;
use App\Services\RouteComposition\Contracts\RouteComposer;
use App\Services\RouteComposition\Data\CompositionPolicy;
use App\Services\RouteComposition\Data\CompositionRequest;
use App\Services\RouteComposition\Exceptions\CompositionException;
use App\Services\RouteComposition\Support\CompositionCatalog;
use App\Services\RouteComposition\Support\CourseRanker;
use App\Services\RouteComposition\Support\DraftAssembler;
use App\Services\RouteComposition\Support\ExclusionGuard;
use App\Services\RouteComposition\Support\RecommendedTopUp;
use App\Services\RouteValidation\Data\RouteDraft;
use Database\Seeders\DocumentTypeSeeder;

/**
 * El gancho: la especialización que el lead pidió, completa, más tres cursos
 * de refuerzo elegidos por la ocupación y el objetivo del lead.
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

        $extras = (new RecommendedTopUp)->for($chosen, $anchor, $request->lead, $request->documentType);
        $courses = Course::with('specialization')->whereIn('id', array_keys($extras))->get()->keyBy('id');

        foreach ($extras as $id => $justification) {
            $course = $courses->get($id);

            if ($course !== null && $course->specialization !== null) {
                $assembler->block($course->specialization, [$course], RouteItemRole::Complementaria, justification: $justification);
            }
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
}
