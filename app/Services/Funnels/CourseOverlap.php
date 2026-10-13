<?php

declare(strict_types=1);

namespace App\Services\Funnels;

use App\Models\Funnel;
use Illuminate\Support\Facades\DB;

/**
 * En qué otros embudos vive cada curso.
 *
 * Un curso puede estar en dos embudos a propósito —el mismo curso engancha desde
 * ramas distintas— así que esto no prohíbe nada: avisa. Lo que evita es lo otro,
 * que alguien arme por tercera vez el mismo paquete sin saber que ya existe, y que
 * dos asesores manden rutas idénticas bajo nombres distintos.
 */
final class CourseOverlap
{
    /**
     * Los embudos que ya contienen cada curso, indexados por curso.
     *
     * @return array<int, list<array{id: int, nombre: string}>>
     */
    public function map(?int $exceptFunnelId = null): array
    {
        $rows = DB::table('funnel_route_courses as items')
            ->join('funnel_routes as routes', 'routes.id', '=', 'items.funnel_route_id')
            ->join('funnels as funnels', 'funnels.id', '=', 'routes.funnel_id')
            ->whereNull('routes.deleted_at')
            ->whereNull('funnels.deleted_at')
            ->when($exceptFunnelId !== null, fn ($q) => $q->where('funnels.id', '!=', $exceptFunnelId))
            ->orderBy('funnels.internal_name')
            ->get(['items.course_id', 'funnels.id as funnel_id', 'funnels.internal_name']);

        $map = [];

        foreach ($rows as $row) {
            $courseId = (int) $row->course_id;
            $map[$courseId] ??= [];

            // El mismo embudo podría llegar dos veces si algún día tiene varias
            // rutas con el mismo curso; en el aviso es un solo renglón.
            foreach ($map[$courseId] as $existing) {
                if ($existing['id'] === (int) $row->funnel_id) {
                    continue 2;
                }
            }

            $map[$courseId][] = [
                'id' => (int) $row->funnel_id,
                'nombre' => (string) $row->internal_name,
            ];
        }

        return $map;
    }

    /**
     * El mapa visto desde un embudo concreto: los demás embudos, nunca él mismo.
     *
     * @return array<int, list<array{id: int, nombre: string}>>
     */
    public function forFunnel(?Funnel $funnel): array
    {
        return $this->map($funnel?->id);
    }
}
