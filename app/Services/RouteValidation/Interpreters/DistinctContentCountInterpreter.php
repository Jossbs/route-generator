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
 * Recalcula las dos cifras que el documento imprime y las contrasta con lo que el
 * generador declaró. La contención hace el trabajo sola: los cuatro cursos de Excel
 * comparten una unidad de contenido, así que suman cuatro cursos y un programa.
 *
 * El conteo debe ser correcto y auditable si el lead se pone a sumar.
 */
final class DistinctContentCountInterpreter implements RuleInterpreter
{
    public static function type(): RuleType
    {
        return RuleType::DistinctContentCount;
    }

    public function evaluate(CatalogRule $rule, ValidationContext $context): RuleEvaluation
    {
        $draft = $context->draft;

        if ($draft->declaredCourseCount === null && $draft->declaredContentUnitCount === null) {
            return RuleEvaluation::skip($rule, 'El borrador no declara cifras que contrastar.');
        }

        $counted = array_filter(
            $draft->itemsInLayers($rule->listParameter('layers')),
            fn (RouteItemDraft $item): bool => $item->countsTowardTotal,
        );

        $courseIds = array_values(array_unique(array_map(
            fn (RouteItemDraft $item): int => $item->courseId,
            $counted,
        )));

        $unitIds = [];
        $unresolved = [];

        foreach ($courseIds as $courseId) {
            $unitId = $context->catalog->contentUnitId($courseId);

            if ($unitId === null) {
                $unresolved[] = $courseId;

                continue;
            }

            $unitIds[$unitId] = true;
        }

        $computed = [
            'course_count' => count($courseIds),
            'content_unit_count' => count($unitIds),
        ];

        $declared = [
            'course_count' => $draft->declaredCourseCount,
            'content_unit_count' => $draft->declaredContentUnitCount,
        ];

        $mismatch = array_filter(
            $declared,
            fn (?int $value, string $key): bool => $value !== null && $value !== $computed[$key],
            ARRAY_FILTER_USE_BOTH,
        );

        if ($mismatch === [] && $unresolved === []) {
            return RuleEvaluation::pass($rule, ['computed' => $computed]);
        }

        return RuleEvaluation::fail($rule, [
            'declared' => $declared,
            'computed' => $computed,
            'mismatched_fields' => array_keys($mismatch),
            'courses_without_content_unit' => $unresolved,
        ]);
    }
}
