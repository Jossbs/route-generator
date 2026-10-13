<?php

declare(strict_types=1);

namespace App\Services\RouteValidation\Contracts;

use App\Models\CatalogRule;
use App\Services\RouteValidation\Data\RuleEvaluation;
use App\Services\RouteValidation\RuleType;
use App\Services\RouteValidation\ValidationContext;

/**
 * Un intérprete no conoce ninguna restricción concreta: conoce una *forma* de
 * restricción. SUPPLY_CHAIN_ROLE y cualquier restricción de rol futura pasan por
 * el mismo código; lo que cambia es la fila de `catalog_rules`.
 */
interface RuleInterpreter
{
    public static function type(): RuleType;

    public function evaluate(CatalogRule $rule, ValidationContext $context): RuleEvaluation;
}
