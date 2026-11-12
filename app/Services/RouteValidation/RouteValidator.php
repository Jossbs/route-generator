<?php

declare(strict_types=1);

namespace App\Services\RouteValidation;

use App\Models\CatalogRule;
use App\Models\LearningRoute;
use App\Services\RouteValidation\Data\RouteDraft;
use App\Services\RouteValidation\Data\RuleEvaluation;
use App\Services\RouteValidation\Data\ValidationReport;
use Illuminate\Support\Collection;

/**
 * El paso obligatorio entre el generador y el render: el generador propone una
 * ruta, el validador la aprueba o la rechaza, y solo entonces se renderiza.
 *
 * No conoce ninguna restricción. Carga las reglas vigentes que apliquen al tipo de
 * documento, resuelve el intérprete de cada una y junta los veredictos.
 *
 * La única excepción a «inválida no se renderiza» son los fallos que el embudo de
 * origen dispensó (`RuleWaivers`): reglas marcadas `waivable`, acotadas a los
 * cursos que el embudo curó. Las ocho de docs/ARQUITECTURA.md §4 nunca lo son.
 */
final class RouteValidator
{
    public function __construct(
        private readonly RuleInterpreterRegistry $registry,
        private readonly AdvisorRecommendationPolicy $recommendations,
    ) {}

    public function validate(RouteDraft $draft): ValidationReport
    {
        $context = ValidationContext::for($draft);

        $evaluations = array_values($this->rulesFor($draft->documentTypeSlug)
            ->map(fn (CatalogRule $rule): RuleEvaluation => $this->registry
                ->get($rule->code, $rule->type)
                ->evaluate($rule, $context))
            ->map(fn (RuleEvaluation $e): RuleEvaluation => $draft->waivers?->covers($e) === true ? $e->waive() : $e)
            ->map(fn (RuleEvaluation $e): RuleEvaluation => $this->recommendations->apply($draft, $e))
            ->all());

        return new ValidationReport($evaluations);
    }

    /**
     * Deja la validación asentada en `route_validations`. Se guarda todo —pass,
     * fail y skip— porque el valor de la bitácora está en poder demostrar después
     * que la regla se evaluó, no solo que no falló.
     */
    public function record(LearningRoute $route, ValidationReport $report): void
    {
        $evaluatedAt = now();

        $route->validations()->delete();

        $route->validations()->createMany(
            array_map(fn (RuleEvaluation $e): array => [
                'rule_code' => $e->ruleCode,
                'result' => $e->result,
                'details' => [
                    ...$e->details,
                    'severity' => $e->severity->value,
                    'message' => $e->message,
                    'waived' => $e->waived,
                ],
                'evaluated_at' => $evaluatedAt,
            ], $report->evaluations),
        );
    }

    /** @return Collection<int, CatalogRule> */
    private function rulesFor(string $documentTypeSlug): Collection
    {
        return CatalogRule::query()
            ->inEffect()
            ->orderBy('code')
            ->get()
            ->filter(fn (CatalogRule $rule): bool => $rule->appliesToDocumentType($documentTypeSlug))
            ->values();
    }
}
