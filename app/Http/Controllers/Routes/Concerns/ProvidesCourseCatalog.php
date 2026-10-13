<?php

declare(strict_types=1);

namespace App\Http\Controllers\Routes\Concerns;

use App\Models\Course;

/**
 * El catálogo que alimenta el botón «Agregar otro curso» de los dos pasos
 * intermedios.
 *
 * Lo comparten la propuesta de la ruta inicial y los meses de la final porque es
 * el mismo buscador sobre el mismo catálogo. Tenerlo dos veces dejaría que uno de
 * los dos empezara a ofrecer cursos inactivos el día que alguien tocara el otro.
 */
trait ProvidesCourseCatalog
{
    /** @return list<array<string, mixed>> */
    protected function catalog(): array
    {
        return array_values(Course::query()
            ->with('specialization')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Course $course): array => [
                'id' => (int) $course->id,
                'nombre' => $course->name,
                'especialidad' => $course->specialization?->name,
                'especialidad_id' => (int) $course->specialization_id,
                'horas' => $course->duration_hours,
            ])
            ->all());
    }
}
