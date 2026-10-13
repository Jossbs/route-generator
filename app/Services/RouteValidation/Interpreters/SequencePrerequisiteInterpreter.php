<?php

declare(strict_types=1);

namespace App\Services\RouteValidation\Interpreters;

use App\Models\CatalogRule;
use App\Services\RouteValidation\Contracts\RuleInterpreter;
use App\Services\RouteValidation\Data\RouteItemDraft;
use App\Services\RouteValidation\Data\RuleEvaluation;
use App\Services\RouteValidation\RuleType;
use App\Services\RouteValidation\ValidationContext;

/**
 * Orden pedagógico del itinerario. Dos comprobaciones sobre la misma secuencia:
 *
 *  1. Niveles: dentro de una misma unidad de contenido, Básico nunca cae después de
 *     Intermedio o Avanzado. Los niveles fuera de `level_order` —`completo`, `unico`—
 *     no participan: un paquete «Completo» no es un escalón de la escalera.
 *  2. Prerrequisitos duros: si el requisito también está en la ruta, tiene que ir
 *     antes. Que no esté no es falta: el lead pudo traerlo aprendido.
 */
final class SequencePrerequisiteInterpreter implements RuleInterpreter
{
    public static function type(): RuleType
    {
        return RuleType::SequencePrerequisite;
    }

    public function evaluate(CatalogRule $rule, ValidationContext $context): RuleEvaluation
    {
        $items = $context->draft->itemsInLayers($rule->listParameter('layers', ['itinerario']));

        if ($items === []) {
            return RuleEvaluation::skip($rule, 'La ruta no tiene renglones en las capas de la regla.');
        }

        usort($items, fn (RouteItemDraft $a, RouteItemDraft $b): int => $a->sequenceKey() <=> $b->sequenceKey());

        /** @var array<int, int> $sequence courseId => posición en la secuencia */
        $sequence = [];
        foreach ($items as $index => $item) {
            $sequence[$item->courseId] ??= $index;
        }

        $violations = [
            ...$this->levelViolations($rule, $context, $items, $sequence),
            ...$this->prerequisiteViolations($rule, $context, $sequence),
        ];

        return $violations === []
            ? RuleEvaluation::pass($rule)
            : RuleEvaluation::fail($rule, ['violations' => $violations]);
    }

    /**
     * @param  list<RouteItemDraft>  $items
     * @param  array<int, int>  $sequence
     * @return list<array<string, mixed>>
     */
    private function levelViolations(CatalogRule $rule, ValidationContext $context, array $items, array $sequence): array
    {
        $levelOrder = $rule->listParameter('level_order', []) ?? [];

        if ($levelOrder === []) {
            return [];
        }

        $groupBy = $rule->stringParameter('group_by', 'content_unit');

        /** @var array<string, list<array{course_id: int, name: ?string, rank: int, seq: int}>> $groups */
        $groups = [];

        foreach ($items as $item) {
            $course = $context->catalog->course($item->courseId);

            if ($course === null) {
                continue;
            }

            $rank = array_search($course->level->value, $levelOrder, true);

            if ($rank === false) {
                continue;
            }

            $groupKey = $groupBy === 'specialization'
                ? 'spec:'.$course->specialization_id
                : 'unit:'.$course->content_unit_id;

            $groups[$groupKey][] = [
                'course_id' => $course->id,
                'name' => $course->name,
                'rank' => $rank,
                'seq' => $sequence[$item->courseId],
            ];
        }

        $violations = [];

        foreach ($groups as $groupKey => $members) {
            foreach ($members as $earlier) {
                foreach ($members as $later) {
                    if ($earlier['rank'] < $later['rank'] && $earlier['seq'] > $later['seq']) {
                        $violations[] = [
                            'kind' => 'level_order',
                            'group' => $groupKey,
                            'expected_first' => $earlier['name'],
                            'expected_first_course_id' => $earlier['course_id'],
                            'placed_first' => $later['name'],
                            'placed_first_course_id' => $later['course_id'],
                        ];
                    }
                }
            }
        }

        return $violations;
    }

    /**
     * @param  array<int, int>  $sequence
     * @return list<array<string, mixed>>
     */
    private function prerequisiteViolations(CatalogRule $rule, ValidationContext $context, array $sequence): array
    {
        if (! $rule->boolParameter('enforce_prerequisites', true)) {
            return [];
        }

        $strengths = $rule->listParameter('prerequisite_strengths', ['duro']) ?? [];
        $violations = [];

        foreach ($context->catalog->prerequisites($strengths) as $courseId => $requiredIds) {
            if (! array_key_exists($courseId, $sequence)) {
                continue;
            }

            foreach ($requiredIds as $requiredId) {
                // Ausente no es falta: el requisito puede venir de fuera de la ruta.
                if (! array_key_exists($requiredId, $sequence)) {
                    continue;
                }

                if ($sequence[$requiredId] > $sequence[$courseId]) {
                    $violations[] = [
                        'kind' => 'prerequisite_order',
                        'course' => $context->catalog->course($courseId)?->name,
                        'course_id' => $courseId,
                        'requires' => $context->catalog->course($requiredId)?->name,
                        'requires_course_id' => $requiredId,
                    ];
                }
            }
        }

        return $violations;
    }
}
