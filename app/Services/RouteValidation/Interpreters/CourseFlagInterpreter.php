<?php

declare(strict_types=1);

namespace App\Services\RouteValidation\Interpreters;

use App\Models\CatalogRule;
use App\Services\RouteValidation\Contracts\RuleInterpreter;
use App\Services\RouteValidation\Data\RuleEvaluation;
use App\Services\RouteValidation\RuleType;
use App\Services\RouteValidation\ValidationContext;

/**
 * Levanta la mano cuando la ruta se apoya en un curso marcado por la auditoría del
 * catálogo.
 *
 * La lista de cursos afectados no vive en la regla sino en la columna: marcar uno
 * nuevo como «duración en disputa» es un UPDATE sobre `courses`, y la regla se
 * entera sola. Duplicar esa lista en `rule_courses` sería tener el mismo dato en
 * dos lugares, que es exactamente lo que se evita.
 *
 * `parameters.flag` se valida contra una lista blanca: es un nombre de columna que
 * llega desde un JSONB y no puede aceptarse a ciegas.
 */
final class CourseFlagInterpreter implements RuleInterpreter
{
    /** Las banderas de auditoría que una regla puede vigilar. */
    private const ALLOWED_FLAGS = ['needs_duration_review', 'needs_overlap_review'];

    public static function type(): RuleType
    {
        return RuleType::CourseFlag;
    }

    public function evaluate(CatalogRule $rule, ValidationContext $context): RuleEvaluation
    {
        $flag = $rule->stringParameter('flag', '');

        if (! in_array($flag, self::ALLOWED_FLAGS, true)) {
            return RuleEvaluation::skip($rule, "La regla no declara una bandera vigilable: [{$flag}].");
        }

        $roles = $rule->applies_to_roles;
        $layers = $rule->listParameter('layers');

        $flagged = [];

        foreach ($context->draft->itemsInLayers($layers) as $item) {
            if ($roles !== null && ! in_array($item->role->value, $roles, true)) {
                continue;
            }

            $course = $context->catalog->course($item->courseId);

            if ($course === null || $course->{$flag} !== true) {
                continue;
            }

            $flagged[$course->id] = [
                'course_id' => $course->id,
                'course' => $course->name,
                'flag' => $flag,
                'note' => $course->analysis_note,
                'layer' => $item->layer->value,
                'role' => $item->role->value,
            ];
        }

        return $flagged === []
            ? RuleEvaluation::pass($rule)
            : RuleEvaluation::fail($rule, ['flagged' => array_values($flagged)]);
    }
}
