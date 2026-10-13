<?php

declare(strict_types=1);

namespace App\Services\RouteValidation;

use App\Services\RouteValidation\Contracts\RuleInterpreter;
use App\Services\RouteValidation\Exceptions\UnknownRuleTypeException;
use App\Services\RouteValidation\Interpreters\CompletenessClaimInterpreter;
use App\Services\RouteValidation\Interpreters\ContainmentOverlapInterpreter;
use App\Services\RouteValidation\Interpreters\CourseFlagInterpreter;
use App\Services\RouteValidation\Interpreters\DistinctContentCountInterpreter;
use App\Services\RouteValidation\Interpreters\MutualExclusionInterpreter;
use App\Services\RouteValidation\Interpreters\PaceCapacityInterpreter;
use App\Services\RouteValidation\Interpreters\PhrasingBanInterpreter;
use App\Services\RouteValidation\Interpreters\PresentationIsolationInterpreter;
use App\Services\RouteValidation\Interpreters\RequiredFieldsInterpreter;
use App\Services\RouteValidation\Interpreters\RoleRestrictionInterpreter;
use App\Services\RouteValidation\Interpreters\SequencePrerequisiteInterpreter;

/**
 * La fuente de verdad de qué tipos de regla sabe ejecutar el sistema.
 * Un tipo nuevo se registra aquí; las restricciones que lo usen siguen siendo filas.
 */
final class RuleInterpreterRegistry
{
    /** @var array<string, RuleInterpreter> */
    private array $interpreters = [];

    /** @param list<RuleInterpreter>|null $interpreters */
    public function __construct(?array $interpreters = null)
    {
        foreach ($interpreters ?? self::defaults() as $interpreter) {
            $this->register($interpreter);
        }
    }

    /** @return list<RuleInterpreter> */
    public static function defaults(): array
    {
        return [
            new MutualExclusionInterpreter,
            new RoleRestrictionInterpreter,
            new DistinctContentCountInterpreter,
            new CompletenessClaimInterpreter,
            new PhrasingBanInterpreter,
            new RequiredFieldsInterpreter,
            new SequencePrerequisiteInterpreter,
            new PresentationIsolationInterpreter,
            new ContainmentOverlapInterpreter,
            new CourseFlagInterpreter,
            new PaceCapacityInterpreter,
        ];
    }

    public function register(RuleInterpreter $interpreter): void
    {
        $this->interpreters[$interpreter::type()->value] = $interpreter;
    }

    public function get(string $ruleCode, string $type): RuleInterpreter
    {
        return $this->interpreters[$type] ?? throw UnknownRuleTypeException::for($ruleCode, $type);
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_keys($this->interpreters);
    }
}
