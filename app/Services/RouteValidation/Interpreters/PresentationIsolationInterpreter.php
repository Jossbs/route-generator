<?php

declare(strict_types=1);

namespace App\Services\RouteValidation\Interpreters;

use App\Models\CatalogRule;
use App\Services\RouteValidation\Contracts\RuleInterpreter;
use App\Services\RouteValidation\Data\RuleEvaluation;
use App\Services\RouteValidation\RuleType;
use App\Services\RouteValidation\ValidationContext;

/**
 * Cada especialización se presenta bajo su propio título.
 *
 * Dos especializaciones pueden compartir un mes —Cadena de Suministros junto a Lean
 * o Kaizen— pero jamás un encabezado: revolverlas borra la identidad de la
 * especialidad que el lead pidió.
 */
final class PresentationIsolationInterpreter implements RuleInterpreter
{
    public static function type(): RuleType
    {
        return RuleType::PresentationIsolation;
    }

    public function evaluate(CatalogRule $rule, ValidationContext $context): RuleEvaluation
    {
        $headings = $context->draft->headings;

        if ($headings === []) {
            return RuleEvaluation::skip($rule, 'El borrador no declara encabezados que inspeccionar.');
        }

        $max = $rule->intParameter('max_specializations_per_heading', 1);
        $violations = [];

        foreach ($headings as $heading) {
            $ids = array_values(array_unique($heading->specializationIds));

            if (count($ids) <= $max) {
                continue;
            }

            $violations[] = [
                'heading' => $heading->label,
                'key' => $heading->key,
                'month' => $heading->month,
                'max_allowed' => $max,
                'specializations' => array_map(
                    fn (int $id): ?string => $context->catalog->specialization($id)?->name,
                    $ids,
                ),
            ];
        }

        return $violations === []
            ? RuleEvaluation::pass($rule)
            : RuleEvaluation::fail($rule, ['violations' => $violations]);
    }
}
