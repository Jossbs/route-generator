<?php

declare(strict_types=1);

namespace App\Services\Funnels;

use App\Models\FunnelRoute;
use Illuminate\Support\Facades\DB;

/**
 * Escribe la composición de una ruta curada.
 *
 * Vive fuera del controlador porque la lista de cursos no es un campo más: es lo
 * que el validador evalúa y lo que termina precargado en el formulario del asesor.
 * Reordenar es reescribir `position`, así que la sincronización se hace completa y
 * dentro de una transacción —nunca a medias— y el orden que llega es el orden que
 * queda.
 *
 * Solo toca lo que la v1 expone. `kind` y `outcome_phrase_override` están dormidos
 * (MODULO_EMBUDOS.md §3.3): las columnas existen para no migrar después, y aquí se
 * quedan con su valor por omisión. Las 64 frases de resultado siguen sin aprobarse
 * y no se fabrican por esta vía.
 */
final class FunnelRouteWriter
{
    /**
     * @param  list<array{course_id: int, suggested_month?: int|null}>  $items  En el orden en que se presentarán.
     */
    public function sync(FunnelRoute $route, array $items): FunnelRoute
    {
        return DB::transaction(function () use ($route, $items): FunnelRoute {
            $seen = [];

            foreach ($items as $item) {
                $courseId = (int) $item['course_id'];

                // Un curso repetido en la lista es un renglón, no dos: la unicidad
                // ya la exige la tabla y contarlo dos veces inflaría las horas.
                if (in_array($courseId, $seen, true)) {
                    continue;
                }

                $seen[] = $courseId;

                $route->items()->updateOrCreate(
                    ['course_id' => $courseId],
                    [
                        'position' => count($seen),
                        'suggested_month' => $item['suggested_month'] ?? null,
                    ],
                );
            }

            $route->items()->whereNotIn('course_id', $seen)->delete();

            return $route->load('items.course');
        });
    }
}
