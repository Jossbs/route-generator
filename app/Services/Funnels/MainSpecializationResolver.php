<?php

declare(strict_types=1);

namespace App\Services\Funnels;

use App\Models\Course;
use App\Models\Specialization;

/**
 * Qué especialidad abre el embudo.
 *
 * Ya no se captura: se deriva de los cursos elegidos, que es de donde sale de
 * todos modos. La que más cursos aporta es la rama que el lead va a percibir como
 * su ruta principal, y es la que ocupa el rol de `ancla` cuando el validador
 * evalúa la lista.
 *
 * Con una excepción que es una restricción de dominio, no una preferencia: una
 * especialidad que no admite el rol de ancla no lo ocupa aunque sea mayoría.
 * Cadena de Suministros tiene un solo curso y no se presenta sola; si fuera el
 * ancla, el documento abriría con un bloque de un curso al lado de otros de ocho.
 * Cede el papel a la siguiente que sí lo admita y se queda como refuerzo, bajo su
 * propio título.
 */
final class MainSpecializationResolver
{
    /** @param list<int> $courseIds */
    public function for(array $courseIds): ?int
    {
        if ($courseIds === []) {
            return null;
        }

        $courses = Course::query()
            ->with('specialization')
            ->whereIn('id', $courseIds)
            ->get();

        if ($courses->isEmpty()) {
            return null;
        }

        $ranked = $courses
            ->groupBy('specialization_id')
            ->map(fn ($group, int|string $id): array => [
                'id' => (int) $id,
                'cursos' => $group->count(),
                'especialidad' => $group->first()->specialization,
            ])
            ->sortBy(fn (array $row): array => [
                -$row['cursos'],
                $row['especialidad']->display_order ?? PHP_INT_MAX,
            ])
            ->values();

        $anchorable = $ranked->first(
            fn (array $row): bool => $row['especialidad'] instanceof Specialization
                && $row['especialidad']->allowsRole('ancla'),
        );

        return $anchorable['id'] ?? $ranked->first()['id'];
    }
}
