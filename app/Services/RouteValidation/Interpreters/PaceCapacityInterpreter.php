<?php

declare(strict_types=1);

namespace App\Services\RouteValidation\Interpreters;

use App\Models\CatalogRule;
use App\Services\RouteValidation\Contracts\RuleInterpreter;
use App\Services\RouteValidation\Data\RuleEvaluation;
use App\Services\RouteValidation\RuleType;
use App\Services\RouteValidation\ValidationContext;

/**
 * Que el itinerario quepa en el tiempo que el miembro dijo que tiene.
 *
 * El documento anual promete un año a N horas por semana. Si la lista pide más,
 * el último mes carga con todo lo que sobra y la recomendación deja de ser
 * sostenible, que es justo lo que las horas capturadas vinieron a asegurar.
 *
 * Las horas se cuentan una vez por programa de contenido —como las cuenta el
 * panel—, así que un paquete junto a uno de sus niveles no pesa doble. Sin ritmo
 * capturado la regla no aplica: las membresías anteriores no lo tienen.
 */
final class PaceCapacityInterpreter implements RuleInterpreter
{
    public static function type(): RuleType
    {
        return RuleType::PaceCapacity;
    }

    public function evaluate(CatalogRule $rule, ValidationContext $context): RuleEvaluation
    {
        $weekly = data_get($context->draft->membership, $rule->stringParameter('weekly_hours_field', 'weekly_hours'));

        if (blank($weekly) || (int) $weekly <= 0) {
            return RuleEvaluation::skip($rule, 'La membresía no trae horas por semana.');
        }

        $months = (int) ($rule->parameters['months'] ?? 12);
        $weeksPerMonth = (float) ($rule->parameters['weeks_per_month'] ?? 4.345);
        $capacity = (int) floor((int) $weekly * $weeksPerMonth * $months);

        $hoursByUnit = [];

        foreach ($context->draft->itemsInLayers($rule->listParameter('layers', ['itinerario'])) as $item) {
            $course = $context->catalog->course($item->courseId);

            if ($course === null) {
                continue;
            }

            $hoursByUnit[$course->content_unit_id] = max(
                $hoursByUnit[$course->content_unit_id] ?? 0,
                $course->duration_hours ?? 0,
            );
        }

        $hours = (int) array_sum($hoursByUnit);
        $details = ['hours' => $hours, 'capacity' => $capacity, 'weekly_hours' => (int) $weekly];

        return $hours <= $capacity
            ? RuleEvaluation::pass($rule, $details)
            : RuleEvaluation::fail($rule, [...$details, 'over' => $hours - $capacity]);
    }
}
