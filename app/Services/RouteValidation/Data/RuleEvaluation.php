<?php

declare(strict_types=1);

namespace App\Services\RouteValidation\Data;

use App\Enums\RuleSeverity;
use App\Enums\ValidationResult;
use App\Models\CatalogRule;

/**
 * El veredicto de una regla sobre una ruta. Se persiste tal cual en
 * `route_validations`: es lo que vuelve auditable "la restricción se respetó".
 *
 * Un fallo dispensado sigue siendo un fallo: se asienta como `fail` con la marca
 * `waived` en el detalle. Lo único que cambia es que deja de bloquear y de pedir
 * confirmación, porque quien curó el embudo ya lo revisó y decidió seguir.
 */
final readonly class RuleEvaluation
{
    /** @param array<string, mixed> $details */
    public function __construct(
        public string $ruleCode,
        public string $ruleType,
        public ValidationResult $result,
        public RuleSeverity $severity,
        public ?string $message = null,
        public array $details = [],
        public bool $waivable = false,
        public bool $waived = false,
    ) {}

    /** @param array<string, mixed> $details */
    public static function pass(CatalogRule $rule, array $details = []): self
    {
        return new self($rule->code, $rule->type, ValidationResult::Pass, $rule->severity, null, $details, (bool) $rule->waivable);
    }

    /** @param array<string, mixed> $details */
    public static function fail(CatalogRule $rule, array $details = []): self
    {
        return new self($rule->code, $rule->type, ValidationResult::Fail, $rule->severity, $rule->message_es, $details, (bool) $rule->waivable);
    }

    /** No aplicaba: faltaba el insumo o la regla no tenía miembros que evaluar. */
    public static function skip(CatalogRule $rule, string $reason): self
    {
        return new self($rule->code, $rule->type, ValidationResult::Skip, $rule->severity, null, ['reason' => $reason], (bool) $rule->waivable);
    }

    public function failed(): bool
    {
        return $this->result === ValidationResult::Fail;
    }

    public function blocks(): bool
    {
        return $this->failed() && ! $this->waived && $this->severity === RuleSeverity::Blocking;
    }

    /** El mismo fallo, ya dispensado por el embudo. */
    public function waive(): self
    {
        return new self(
            $this->ruleCode,
            $this->ruleType,
            $this->result,
            $this->severity,
            $this->message,
            [...$this->details, 'waived' => true],
            $this->waivable,
            true,
        );
    }

    /**
     * Los cursos involucrados, rastreados dentro del detalle del intérprete.
     *
     * Cada tipo de regla arma su detalle a su manera —`conflicting`, `overlaps`,
     * `violations`, `flagged`— así que en vez de un caso por tipo se recogen todas
     * las claves que terminan en `course_id` a cualquier profundidad. Un tipo
     * nuevo queda cubierto sin tocar esto.
     *
     * @return list<int>
     */
    public function courseIds(): array
    {
        $ids = [];
        $details = $this->details;

        array_walk_recursive($details, function (mixed $value, int|string $key) use (&$ids): void {
            if (is_int($value) && str_ends_with((string) $key, 'course_id')) {
                $ids[] = $value;
            }
        });

        return array_values(array_unique($ids));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'rule_code' => $this->ruleCode,
            'rule_type' => $this->ruleType,
            'result' => $this->result->value,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'details' => $this->details,
            'waived' => $this->waived,
        ];
    }
}
