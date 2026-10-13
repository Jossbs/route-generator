<?php

declare(strict_types=1);

namespace App\Services\RouteGeneration;

use App\Models\Funnel;
use App\Models\FunnelRoute;
use App\Services\RouteComposition\Exceptions\CompositionException;
use App\Services\RouteGeneration\Data\LeadCapture;

/**
 * El embudo que el asesor eligió, ya resuelto a su ruta curada.
 *
 * Un embudo sin cursos no puede sembrar nada. El selector no los ofrece, así que
 * llegar aquí significa que lo vaciaron mientras el formulario estaba abierto: el
 * asesor lee qué pasó, no una excepción.
 *
 * Vive aparte porque la condición es la misma cuando se propone y cuando se emite,
 * y son dos servicios distintos los que la aplican. Escrita dos veces terminaría
 * aplicándose distinto: la propuesta admitiría lo que la emisión rechaza, y el
 * asesor se enteraría con el lead esperando del otro lado de WhatsApp.
 */
final class FunnelRouteLocator
{
    /** @throws CompositionException */
    public function for(LeadCapture $capture): FunnelRoute
    {
        $funnel = Funnel::query()->findOrFail($capture->funnelId);

        $route = $funnel->primaryRoute()
            ->with('items.course', 'funnel', 'archetype', 'mainSpecialization')
            ->first();

        if ($route === null || $route->orderedCourseIds() === []) {
            throw CompositionException::funnelWithoutCourses($funnel->internal_name);
        }

        return $route;
    }
}
