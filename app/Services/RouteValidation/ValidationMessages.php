<?php

declare(strict_types=1);

namespace App\Services\RouteValidation;

use App\Enums\RuleSeverity;
use App\Enums\ValidationResult;
use App\Models\CatalogRule;
use App\Models\LearningRoute;
use App\Services\RouteValidation\Data\RuleEvaluation;

/** Explica los resultados con los cursos involucrados y su gravedad real. */
final class ValidationMessages
{
    public function message(RuleEvaluation $evaluation): string
    {
        if ($evaluation->details['advisor_recommendation'] ?? false) {
            return $evaluation->message ?? 'Revisa los adicionales según el objetivo del lead; puedes continuar.';
        }

        if ($evaluation->ruleCode === 'LEADERSHIP_OVERLAP') {
            $names = array_values(array_unique(array_filter(array_column($evaluation->details['conflicting'] ?? [], 'course'), 'is_string')));

            if (count($names) > 1) {
                return implode(' y ', array_map(fn (string $name): string => '«'.$name.'»', $names))
                    .' se traslapan en contenido. Conserva solo uno en la recomendación.';
            }
        }

        if ($evaluation->ruleCode === 'PREREQUISITE_SUGGESTED') {
            $messages = [];

            foreach ($evaluation->details['violations'] ?? [] as $violation) {
                if (isset($violation['requires'], $violation['course'])) {
                    $messages[] = 'Se recomienda cursar «'.$violation['requires'].'» antes de «'.$violation['course'].'».';
                }
            }

            if ($messages !== []) {
                return implode(' ', array_unique($messages)).' Puedes continuar si el lead ya tiene esa base.';
            }
        }

        return $evaluation->message ?? 'Revisa los cursos seleccionados: no cumplen una regla del catálogo.';
    }

    /** @return array{bloqueos: list<array{codigo: string, mensaje: string}>, advertencias: list<array{codigo: string, mensaje: string}>} */
    public function recorded(LearningRoute $route): array
    {
        $route->loadMissing('validations');
        $rules = CatalogRule::whereIn('code', $route->validations->pluck('rule_code'))->get()->keyBy('code');
        $warningRows = $route->snapshot['avisos_validador'] ?? [];
        $warnings = collect(is_array($warningRows) ? $warningRows : [])->keyBy('codigo');
        $result = ['bloqueos' => [], 'advertencias' => []];

        foreach ($route->validations as $validation) {
            $details = $validation->details ?? [];

            if ($validation->result !== ValidationResult::Fail || ($details['waived'] ?? false)) {
                continue;
            }

            $rule = $rules->get($validation->rule_code);
            $severity = RuleSeverity::tryFrom($details['severity'] ?? ($warnings->has($validation->rule_code) ? 'warning' : $rule?->severity->value ?? 'blocking'))
                ?? RuleSeverity::Blocking;
            $evaluation = new RuleEvaluation(
                $validation->rule_code,
                'recorded',
                ValidationResult::Fail,
                $severity,
                $details['message'] ?? $warnings->get($validation->rule_code)['mensaje'] ?? $rule?->message_es,
                $details,
            );
            $result[$severity === RuleSeverity::Blocking ? 'bloqueos' : 'advertencias'][] = [
                'codigo' => $evaluation->ruleCode,
                'mensaje' => $this->message($evaluation),
            ];
        }

        return $result;
    }
}
