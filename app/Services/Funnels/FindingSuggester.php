<?php

declare(strict_types=1);

namespace App\Services\Funnels;

use App\Models\Course;
use App\Services\RouteValidation\Data\RuleEvaluation;

/**
 * Propone cómo corregir un hallazgo del validador, listo para aplicarse con un clic.
 *
 * Cada sugerencia es la lista de cursos completa tal como quedaría: el armador la
 * pone en pantalla y se guarda con el botón de siempre, así que nada se escribe
 * sin que alguien lo vea.
 *
 * Igual que el panel, no conoce reglas por código: lee la forma del detalle que
 * arma cada intérprete —`conflicting`, `overlaps`, `violations`—. Una regla nueva
 * de un tipo existente trae sus sugerencias sin tocar esto; un detalle que no
 * reconoce simplemente no sugiere nada.
 */
final class FindingSuggester
{
    private const MAX = 3;

    /** @var array<int, string> */
    private array $names = [];

    /**
     * @param  list<int>  $courseIds  La lista actual, en orden.
     * @return list<array{etiqueta: string, curso_ids: list<int>}>
     */
    public function for(RuleEvaluation $evaluation, array $courseIds): array
    {
        $details = $evaluation->details;
        $this->loadNames($evaluation->courseIds());

        $suggestions = match (true) {
            isset($details['conflicting']) => $this->forConflict($details, $courseIds),
            isset($details['overlaps']) => $this->forOverlaps($details['overlaps'], $courseIds),
            isset($details['violations']) => $this->forViolations($details['violations'], $courseIds),
            default => [],
        };

        // Nada que deje la ruta vacía o igual que estaba.
        $suggestions = array_filter(
            $suggestions,
            fn (array $s): bool => $s['curso_ids'] !== [] && $s['curso_ids'] !== $courseIds,
        );

        return array_slice(array_values($suggestions), 0, self::MAX);
    }

    /**
     * Varios cursos que no pueden ir juntos: quedarse con uno, o quitar los que
     * sobran si la regla admite más de uno.
     *
     * @param  array<string, mixed>  $details
     * @param  list<int>  $courseIds
     * @return list<array{etiqueta: string, curso_ids: list<int>}>
     */
    private function forConflict(array $details, array $courseIds): array
    {
        $present = array_values(array_intersect(
            array_map(fn (array $c): int => (int) $c['course_id'], $details['conflicting']),
            $courseIds,
        ));
        $max = (int) ($details['max_allowed'] ?? 1);

        if ($max === 1) {
            return array_map(fn (int $keep): array => [
                'etiqueta' => 'Quedarse solo con '.$this->name($keep),
                'curso_ids' => $this->without($courseIds, array_diff($present, [$keep])),
            ], $present);
        }

        return array_map(fn (int $drop): array => [
            'etiqueta' => 'Quitar '.$this->name($drop),
            'curso_ids' => $this->without($courseIds, [$drop]),
        ], $present);
    }

    /**
     * Un paquete junto a lo que ya contiene: quedarse con el paquete, o con sus
     * partes si solo choca una.
     *
     * @param  list<array<string, mixed>>  $overlaps
     * @param  list<int>  $courseIds
     * @return list<array{etiqueta: string, curso_ids: list<int>}>
     */
    private function forOverlaps(array $overlaps, array $courseIds): array
    {
        $childrenByParent = [];

        foreach ($overlaps as $overlap) {
            $childrenByParent[(int) $overlap['parent_course_id']][] = (int) $overlap['child_course_id'];
        }

        $suggestions = [];

        foreach ($childrenByParent as $parent => $children) {
            $suggestions[] = [
                'etiqueta' => count($children) === 1
                    ? 'Quitar '.$this->name($children[0]).': ya viene en '.$this->name($parent)
                    : 'Quitar lo que ya incluye '.$this->name($parent),
                'curso_ids' => $this->without($courseIds, $children),
            ];

            $suggestions[] = [
                'etiqueta' => 'Quitar '.$this->name($parent),
                'curso_ids' => $this->without($courseIds, [$parent]),
            ];
        }

        return $suggestions;
    }

    /**
     * Cursos fuera de orden se acomodan; cursos en un lugar que no les toca se
     * quitan.
     *
     * @param  list<array<string, mixed>>  $violations
     * @param  list<int>  $courseIds
     * @return list<array{etiqueta: string, curso_ids: list<int>}>
     */
    private function forViolations(array $violations, array $courseIds): array
    {
        $moves = [];
        $drops = [];

        foreach ($violations as $violation) {
            match ($violation['kind'] ?? null) {
                'level_order' => $moves[] = [$violation['expected_first_course_id'] ?? null, $violation['placed_first_course_id'] ?? null],
                'prerequisite_order' => $moves[] = [$violation['requires_course_id'] ?? null, $violation['course_id'] ?? null],
                default => isset($violation['course_id']) ? $drops[] = (int) $violation['course_id'] : null,
            };
        }

        $moves = array_values(array_filter($moves, fn (array $m): bool => is_int($m[0]) && is_int($m[1])));

        $suggestions = [];

        if ($moves !== []) {
            $suggestions[] = [
                'etiqueta' => count($moves) === 1
                    ? 'Mover '.$this->name($moves[0][0]).' antes de '.$this->name($moves[0][1])
                    : 'Acomodar los cursos en orden',
                'curso_ids' => $this->reorder($courseIds, $moves),
            ];
        }

        foreach (array_unique($drops) as $drop) {
            $suggestions[] = [
                'etiqueta' => 'Quitar '.$this->name($drop),
                'curso_ids' => $this->without($courseIds, [$drop]),
            ];
        }

        return $suggestions;
    }

    /**
     * Pone cada curso que debe ir primero justo antes del que lo rebasó. Se repite
     * porque una corrección puede descolocar otra; el tope evita un ciclo si el
     * catálogo llegara a declarar prerrequisitos circulares.
     *
     * @param  list<int>  $courseIds
     * @param  list<array{0: int, 1: int}>  $moves
     * @return list<int>
     */
    private function reorder(array $courseIds, array $moves): array
    {
        for ($pass = 0; $pass < count($moves) + 1; $pass++) {
            $changed = false;

            foreach ($moves as [$first, $then]) {
                $from = array_search($first, $courseIds, true);
                $to = array_search($then, $courseIds, true);

                if ($from === false || $to === false || $from < $to) {
                    continue;
                }

                array_splice($courseIds, $from, 1);
                array_splice($courseIds, $to, 0, [$first]);
                $changed = true;
            }

            if (! $changed) {
                break;
            }
        }

        return $courseIds;
    }

    /**
     * @param  list<int>  $courseIds
     * @param  array<int>  $drop
     * @return list<int>
     */
    private function without(array $courseIds, array $drop): array
    {
        return array_values(array_diff($courseIds, $drop));
    }

    /** @param list<int> $ids */
    private function loadNames(array $ids): void
    {
        $missing = array_diff($ids, array_keys($this->names));

        if ($missing !== []) {
            $this->names += Course::whereIn('id', $missing)->pluck('name', 'id')->all();
        }
    }

    private function name(int $id): string
    {
        return $this->names[$id] ?? "el curso {$id}";
    }
}
