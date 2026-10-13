<?php

declare(strict_types=1);

namespace App\Services\RouteValidation\Interpreters;

use App\Enums\RuleSeverity;
use App\Models\CatalogRule;
use App\Services\RouteValidation\Contracts\RuleInterpreter;
use App\Services\RouteValidation\Data\RuleEvaluation;
use App\Services\RouteValidation\RuleType;
use App\Services\RouteValidation\ValidationContext;

/**
 * Escanea el texto ensamblado contra `banned_phrases`.
 *
 * Es la mitad verificable de la restricción de fraseo: si el copy viviera dentro de
 * una plantilla Blade, no habría nada que escanear. Cubre tanto la equivalencia de
 * certificación como las anclas de precio, que el PDF no imprime.
 *
 * Una frase marcada `warning` se reporta sin bloquear; solo las `blocking` reprueban.
 */
final class PhrasingBanInterpreter implements RuleInterpreter
{
    public static function type(): RuleType
    {
        return RuleType::PhrasingBan;
    }

    public function evaluate(CatalogRule $rule, ValidationContext $context): RuleEvaluation
    {
        $text = $context->draft->assembledText();

        if ($text === '') {
            return RuleEvaluation::skip($rule, 'El borrador no trae texto ensamblado que escanear.');
        }

        $phrases = $context->catalog->bannedPhrases($rule->listParameter('scopes'));

        if ($phrases->isEmpty()) {
            return RuleEvaluation::skip($rule, 'No hay frases prohibidas activas en el alcance de la regla.');
        }

        $blocking = [];
        $warnings = [];

        foreach ($phrases as $phrase) {
            if (preg_match($phrase->pattern, $text, $matches) !== 1) {
                continue;
            }

            $hit = [
                'pattern' => $phrase->pattern,
                'scope' => $phrase->scope,
                'matched' => $matches[0],
                'reason' => $phrase->reason,
                'suggested_replacement' => $phrase->suggested_replacement,
            ];

            if ($phrase->severity === RuleSeverity::Blocking) {
                $blocking[] = $hit;
            } else {
                $warnings[] = $hit;
            }
        }

        if ($blocking !== []) {
            return RuleEvaluation::fail($rule, ['violations' => $blocking, 'warnings' => $warnings]);
        }

        return RuleEvaluation::pass($rule, $warnings === [] ? [] : ['warnings' => $warnings]);
    }
}
