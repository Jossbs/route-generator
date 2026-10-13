<?php

declare(strict_types=1);

namespace App\Services\RouteValidation\Interpreters;

use App\Models\CatalogRule;
use App\Models\Specialization;
use App\Services\RouteValidation\Contracts\RuleInterpreter;
use App\Services\RouteValidation\Data\RuleEvaluation;
use App\Services\RouteValidation\RuleType;
use App\Services\RouteValidation\ValidationContext;

/**
 * Impide prometer completa una especialización que el catálogo marca como parcial.
 *
 * Core Tools publica cuatro de las cinco herramientas AIAG —le faltan APQP y MSA—,
 * y un lead de cadena de suministro automotriz lo sabe. El intérprete no nombra a
 * Core Tools: escanea el texto ensamblado por cada especialización cuyo
 * `completeness_status` sea `partial`.
 *
 * El marcador `{name}` de cada patrón se sustituye por el nombre de la
 * especialización, ya escapado.
 */
final class CompletenessClaimInterpreter implements RuleInterpreter
{
    public static function type(): RuleType
    {
        return RuleType::CompletenessClaim;
    }

    public function evaluate(CatalogRule $rule, ValidationContext $context): RuleEvaluation
    {
        $patterns = $rule->listParameter('claim_patterns', []) ?? [];
        $text = $context->draft->assembledText();

        if ($patterns === []) {
            return RuleEvaluation::skip($rule, 'La regla no declara `claim_patterns`.');
        }

        if ($text === '') {
            return RuleEvaluation::skip($rule, 'El borrador no trae texto ensamblado que escanear.');
        }

        $subjects = $this->subjects($rule, $context);

        if ($subjects === []) {
            return RuleEvaluation::skip($rule, 'Ninguna especialización parcial en el alcance de la regla.');
        }

        $violations = [];

        foreach ($subjects as $specialization) {
            foreach ($patterns as $pattern) {
                $resolved = str_replace('{name}', preg_quote($specialization->name, '/'), $pattern);

                if (preg_match($resolved, $text, $matches) === 1) {
                    $violations[] = [
                        'specialization_id' => $specialization->id,
                        'specialization' => $specialization->name,
                        'missing_components' => $specialization->missing_components,
                        'pattern' => $pattern,
                        'matched' => $matches[0],
                    ];
                }
            }
        }

        return $violations === []
            ? RuleEvaluation::pass($rule)
            : RuleEvaluation::fail($rule, ['violations' => $violations]);
    }

    /**
     * Sujetos declarados, filtrados a los que de verdad están incompletos. Sin
     * sujetos, se examina cualquier especialización parcial que toque la ruta.
     *
     * @return list<Specialization>
     */
    private function subjects(CatalogRule $rule, ValidationContext $context): array
    {
        $declared = $rule->specializations()->wherePivot('role', 'subject')->get();

        $candidates = $declared->isNotEmpty()
            ? $declared
            : $context->catalog->specializations();

        return array_values($candidates->filter(fn (Specialization $s): bool => $s->isPartial())->all());
    }
}
