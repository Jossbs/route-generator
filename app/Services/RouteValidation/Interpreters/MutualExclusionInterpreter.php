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
 * Un conjunto de cursos que no pueden coincidir. La regla trae sus miembros en
 * `rule_courses`; `applies_to_roles` acota sobre qué roles se evalúa.
 *
 * Eso es lo que hace convivir LEADERSHIP_OVERLAP con el documento anual: la
 * exclusión corre sobre los roles de recomendación, no sobre `incluido`, así que
 * el inventario puede listar los tres cursos de liderazgo mientras el itinerario
 * recomienda solo el que corresponde al puesto del lead.
 */
final class MutualExclusionInterpreter implements RuleInterpreter
{
    public static function type(): RuleType
    {
        return RuleType::MutualExclusion;
    }

    public function evaluate(CatalogRule $rule, ValidationContext $context): RuleEvaluation
    {
        $memberIds = $rule->courses()->wherePivot('role', 'member')->pluck('courses.id')->all();

        if ($memberIds === []) {
            return RuleEvaluation::skip($rule, 'La regla no declara cursos miembro.');
        }

        $roles = $rule->applies_to_roles;
        $layers = $rule->listParameter('layers');
        $maxAllowed = $rule->intParameter('max_allowed', 1);

        $present = [];

        foreach ($context->draft->itemsInLayers($layers) as $item) {
            if ($roles !== null && ! in_array($item->role->value, $roles, true)) {
                continue;
            }

            if (in_array($item->courseId, $memberIds, true)) {
                $present[$item->courseId] = $item;
            }
        }

        if (count($present) <= $maxAllowed) {
            return RuleEvaluation::pass($rule, ['present' => array_keys($present)]);
        }

        return RuleEvaluation::fail($rule, [
            'max_allowed' => $maxAllowed,
            'evaluated_roles' => $roles,
            'conflicting' => array_map(
                fn (RouteItemDraft $item): array => [
                    'course_id' => $item->courseId,
                    'course' => $context->catalog->course($item->courseId)?->name,
                    'role' => $item->role->value,
                    'layer' => $item->layer->value,
                ],
                array_values($present),
            ),
        ]);
    }
}
