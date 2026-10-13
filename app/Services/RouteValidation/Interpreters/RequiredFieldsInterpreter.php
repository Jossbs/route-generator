<?php

declare(strict_types=1);

namespace App\Services\RouteValidation\Interpreters;

use App\Models\CatalogRule;
use App\Services\RouteValidation\Contracts\RuleInterpreter;
use App\Services\RouteValidation\Data\RuleEvaluation;
use App\Services\RouteValidation\RuleType;
use App\Services\RouteValidation\ValidationContext;

/**
 * Campos sin los cuales el documento no se genera.
 *
 * El anual exige nombre completo, ocupación e inicio y fin de vigencia: sin ellos
 * la portada miente o queda hueca, y es el documento que el miembro conserva.
 */
final class RequiredFieldsInterpreter implements RuleInterpreter
{
    public static function type(): RuleType
    {
        return RuleType::RequiredFields;
    }

    public function evaluate(CatalogRule $rule, ValidationContext $context): RuleEvaluation
    {
        $fields = $rule->listParameter('fields', []) ?? [];
        $source = $rule->stringParameter('source', 'membership');

        if ($fields === []) {
            return RuleEvaluation::skip($rule, 'La regla no declara `fields`.');
        }

        $payload = match ($source) {
            'membership' => $context->draft->membership,
            default => null,
        };

        $missing = array_values(array_filter(
            $fields,
            fn (string $field): bool => blank(data_get($payload, $field)),
        ));

        return $missing === []
            ? RuleEvaluation::pass($rule)
            : RuleEvaluation::fail($rule, ['source' => $source, 'missing' => $missing]);
    }
}
