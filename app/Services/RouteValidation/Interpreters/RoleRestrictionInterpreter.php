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
 * Verifica que ninguna especialización ocupe un rol de presentación que su ficha
 * no permite. La lista blanca vive en `specializations.allowed_roles`, no aquí:
 * Cadena de Suministros lleva `ancla:false, complementaria:false` y por eso queda
 * fuera de esos dos roles sin que ninguna línea de código la nombre.
 *
 * `parameters.role_map` traduce el par capa:rol del renglón a la clave de
 * `allowed_roles`. Un par sin traducción no se evalúa.
 *
 * Con `parameters.check_course_layers`, la misma regla vigila además la lista
 * blanca de capas de cada curso (`courses.allowed_layers`). Es la misma idea un
 * nivel más abajo: el dato dice dónde puede aparecer, el intérprete solo compara.
 */
final class RoleRestrictionInterpreter implements RuleInterpreter
{
    public static function type(): RuleType
    {
        return RuleType::RoleRestriction;
    }

    public function evaluate(CatalogRule $rule, ValidationContext $context): RuleEvaluation
    {
        $roleMap = $rule->mapParameter('role_map');
        $checksCourseLayers = $rule->boolParameter('check_course_layers', false);

        if ($roleMap === [] && ! $checksCourseLayers) {
            return RuleEvaluation::skip($rule, 'La regla no declara `role_map` ni `check_course_layers`.');
        }

        // Sin sujetos declarados, la regla vale para todo miembro presente.
        $subjectSpecializations = $rule->specializations()->wherePivot('role', 'subject')->pluck('specializations.id')->all();
        $subjectCourses = $rule->courses()->wherePivot('role', 'subject')->pluck('courses.id')->all();

        $violations = [];

        foreach ($context->draft->items as $item) {
            if ($roleMap !== [] && ($subjectSpecializations === [] || in_array($item->specializationId, $subjectSpecializations, true))) {
                $violations = [...$violations, ...$this->specializationViolation($item, $roleMap, $context)];
            }

            if ($checksCourseLayers && ($subjectCourses === [] || in_array($item->courseId, $subjectCourses, true))) {
                $violations = [...$violations, ...$this->courseLayerViolation($item, $context)];
            }
        }

        return $violations === []
            ? RuleEvaluation::pass($rule)
            : RuleEvaluation::fail($rule, ['violations' => $violations]);
    }

    /**
     * @param  array<string, string>  $roleMap
     * @return list<array<string, mixed>>
     */
    private function specializationViolation(RouteItemDraft $item, array $roleMap, ValidationContext $context): array
    {
        $presentationRole = $roleMap["{$item->layer->value}:{$item->role->value}"] ?? null;

        if ($presentationRole === null) {
            return [];
        }

        $specialization = $context->catalog->specialization($item->specializationId);

        if ($specialization === null || $specialization->allowsRole($presentationRole)) {
            return [];
        }

        return [[
            'subject' => 'specialization',
            'specialization_id' => $item->specializationId,
            'specialization' => $specialization->name,
            'layer' => $item->layer->value,
            'role' => $item->role->value,
            'presentation_role' => $presentationRole,
            'course_id' => $item->courseId,
        ]];
    }

    /** @return list<array<string, mixed>> */
    private function courseLayerViolation(RouteItemDraft $item, ValidationContext $context): array
    {
        $course = $context->catalog->course($item->courseId);

        if ($course === null || $course->allowsLayer($item->layer)) {
            return [];
        }

        return [[
            'subject' => 'course',
            'course_id' => $item->courseId,
            'course' => $course->name,
            'layer' => $item->layer->value,
            'role' => $item->role->value,
            'allowed_layers' => $course->allowed_layers,
        ]];
    }
}
