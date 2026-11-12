<?php

declare(strict_types=1);

namespace App\Services\RouteValidation;

use App\Enums\RouteItemRole;
use App\Enums\RuleSeverity;
use App\Services\RouteValidation\Data\RouteDraft;
use App\Services\RouteValidation\Data\RuleEvaluation;
use Database\Seeders\DocumentTypeSeeder;

/** El asesor decide los adicionales; los hallazgos siguen visibles y auditados. */
final class AdvisorRecommendationPolicy
{
    public function apply(RouteDraft $draft, RuleEvaluation $evaluation): RuleEvaluation
    {
        if (! $draft->advisorCanChooseExtras || $draft->documentTypeSlug !== DocumentTypeSeeder::ENFOCADA || ! $evaluation->blocks()) {
            return $evaluation;
        }

        $extras = array_values(array_map(fn ($item): int => $item->courseId, array_filter($draft->items, fn ($item): bool => $item->role === RouteItemRole::Complementaria)));
        $message = match ($evaluation->ruleCode) {
            'LEADERSHIP_OVERLAP', 'DMAIC_OVERLAP' => 'Algunos cursos seleccionados tienen contenido relacionado. Puedes conservarlos si responden al objetivo del lead.',
            'PACKAGE_COMPONENT_OVERLAP' => 'Un curso Completo ya incluye alguno de los niveles seleccionados. Puedes conservarlos; las horas y programas se contabilizan sin duplicar contenido.',
            'LEVEL_SEQUENCE' => 'El orden elegido difiere de la secuencia sugerida de niveles o prerrequisitos. Puedes continuar según la experiencia y necesidades del lead.',
            'COURSE_LAYER_RESTRICTION' => 'Algún adicional no tiene duración publicada o no suele recomendarse en esta ruta. Puedes incluirlo; la estimación solo suma las horas disponibles.',
            'SUPPLY_CHAIN_ROLE' => 'Esta especialidad suele presentarse como apoyo de otra. Puedes incluirla como adicional según el objetivo del lead.',
            default => null,
        };
        if ($message === null || $extras === [] || ! $this->onlyAdditionalConflicts($evaluation, $extras)) {
            return $evaluation;
        }

        $names = [];
        $details = $evaluation->details;
        array_walk_recursive($details, function (mixed $value, int|string $key) use (&$names): void {
            if (is_string($value) && in_array($key, ['course', 'parent', 'child', 'expected_first', 'placed_first', 'requires'], true)) {
                $names[] = $value;
            }
        });
        if ($names !== []) {
            $message .= ' Cursos: '.implode(', ', array_unique($names)).'.';
        }

        return new RuleEvaluation(
            $evaluation->ruleCode, $evaluation->ruleType, $evaluation->result,
            RuleSeverity::Warning, $message,
            [...$evaluation->details, 'advisor_recommendation' => true, 'original_severity' => $evaluation->severity->value],
            $evaluation->waivable, $evaluation->waived,
        );
    }

    /** @param list<int> $extras */
    private function onlyAdditionalConflicts(RuleEvaluation $evaluation, array $extras): bool
    {
        if (isset($evaluation->details['conflicting'])) {
            $primary = array_filter($evaluation->details['conflicting'], fn (array $row): bool => ! in_array($row['course_id'], $extras, true));

            return count($primary) <= ($evaluation->details['max_allowed'] ?? 1);
        }

        $issues = $evaluation->details['overlaps'] ?? $evaluation->details['violations'] ?? [];
        if ($issues === []) {
            return false;
        }
        foreach ($issues as $issue) {
            $ids = [];
            array_walk_recursive($issue, function (mixed $value, int|string $key) use (&$ids): void {
                if (is_int($value) && str_ends_with((string) $key, 'course_id')) {
                    $ids[] = $value;
                }
            });
            if (array_intersect($ids, $extras) === []) {
                return false;
            }
        }

        return true;
    }
}
