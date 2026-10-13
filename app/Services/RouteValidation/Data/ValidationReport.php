<?php

declare(strict_types=1);

namespace App\Services\RouteValidation\Data;

use Illuminate\Support\Collection;

/**
 * El resultado completo de una validación. `passes()` es la única puerta hacia el
 * render: si es falso, la ruta no se persiste como `validated` ni entra a la cola.
 */
final readonly class ValidationReport
{
    /** @param list<RuleEvaluation> $evaluations */
    public function __construct(public array $evaluations) {}

    public function passes(): bool
    {
        return $this->blockingFailures()->isEmpty();
    }

    /** @return Collection<int, RuleEvaluation> */
    public function blockingFailures(): Collection
    {
        return collect($this->evaluations)->filter(fn (RuleEvaluation $e): bool => $e->blocks())->values();
    }

    /**
     * Fallos que no bloquean: se registran y se muestran, pero dejan renderizar.
     *
     * @return Collection<int, RuleEvaluation>
     */
    public function warnings(): Collection
    {
        return collect($this->evaluations)
            ->filter(fn (RuleEvaluation $e): bool => $e->failed() && ! $e->waived && ! $e->blocks())
            ->values();
    }

    /**
     * Fallos que el embudo dispensó: ni bloquean ni piden confirmación, pero se
     * asientan y se muestran aparte.
     *
     * @return Collection<int, RuleEvaluation>
     */
    public function waived(): Collection
    {
        return collect($this->evaluations)->filter(fn (RuleEvaluation $e): bool => $e->waived)->values();
    }

    /** @return list<string> */
    public function blockingMessages(): array
    {
        return array_values($this->blockingFailures()
            ->map(fn (RuleEvaluation $e): string => $e->message ?? $e->ruleCode)
            ->all());
    }

    public function for(string $ruleCode): ?RuleEvaluation
    {
        return collect($this->evaluations)->firstWhere('ruleCode', $ruleCode);
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(fn (RuleEvaluation $e): array => $e->toArray(), $this->evaluations);
    }
}
