<?php

declare(strict_types=1);

namespace App\Services\RouteValidation\Interpreters;

use App\Models\CatalogRule;
use App\Services\RouteValidation\Contracts\RuleInterpreter;
use App\Services\RouteValidation\Data\RouteItemDraft;
use App\Services\RouteValidation\Data\RuleEvaluation;
use App\Services\RouteValidation\RuleType;
use App\Services\RouteValidation\ValidationContext;

/**
 * Un curso que agrega a otros no puede aparecer junto a los que absorbe.
 *
 * No es exclusión mutua: entre los componentes no hay conflicto —Excel Básico e
 * Intermedio conviven perfectamente— y lo que sobra es el par paquete↔componente.
 * `mutual_exclusion` no sabe expresar eso, y forzarla prohibiría convivencias
 * legítimas.
 *
 * Dos fuentes de pares, según `parameters.source`:
 *
 *  - `containments` lee `course_containments`, que es donde el catálogo declara
 *    las contenciones confirmadas: los tres paquetes «Completo» de Excel, AutoCAD
 *    y SolidWorks. Confirmar una nueva es una fila, no un deploy.
 *  - `members` lee `rule_courses` con los roles `parent` y `component`. Es para
 *    los traslapes sospechados y sin confirmar —Lean sobre sus ocho cursos,
 *    Kaizen sobre 5'S y OEE—, que no están en `course_containments` justamente
 *    porque el sitio no declara la relación y no se inventa una que no publica.
 */
final class ContainmentOverlapInterpreter implements RuleInterpreter
{
    public static function type(): RuleType
    {
        return RuleType::ContainmentOverlap;
    }

    public function evaluate(CatalogRule $rule, ValidationContext $context): RuleEvaluation
    {
        $pairs = $this->pairs($rule, $context);

        if ($pairs === []) {
            return RuleEvaluation::skip($rule, 'La regla no resuelve ningún par paquete → componente.');
        }

        $roles = $rule->applies_to_roles;
        $layers = $rule->listParameter('layers');

        /** @var array<int, RouteItemDraft> $present */
        $present = [];

        foreach ($context->draft->itemsInLayers($layers) as $item) {
            if ($roles !== null && ! in_array($item->role->value, $roles, true)) {
                continue;
            }

            $present[$item->courseId] = $item;
        }

        $overlaps = [];

        foreach ($pairs as $pair) {
            if (! isset($present[$pair['parent']], $present[$pair['child']])) {
                continue;
            }

            $overlaps[] = [
                'parent_course_id' => $pair['parent'],
                'parent' => $context->catalog->course($pair['parent'])?->name,
                'child_course_id' => $pair['child'],
                'child' => $context->catalog->course($pair['child'])?->name,
                'containment_type' => $pair['type'],
                'layer' => $present[$pair['parent']]->layer->value,
            ];
        }

        return $overlaps === []
            ? RuleEvaluation::pass($rule, ['pairs_checked' => count($pairs)])
            : RuleEvaluation::fail($rule, ['overlaps' => $overlaps]);
    }

    /**
     * @return list<array{parent: int, child: int, type: string}>
     */
    private function pairs(CatalogRule $rule, ValidationContext $context): array
    {
        if ($rule->stringParameter('source', 'containments') === 'containments') {
            return $context->catalog->containments();
        }

        $parents = $rule->courses()->wherePivot('role', 'parent')->pluck('courses.id')->all();
        $components = $rule->courses()->wherePivot('role', 'component')->pluck('courses.id')->all();

        $pairs = [];

        foreach ($parents as $parent) {
            foreach ($components as $component) {
                if ($parent !== $component) {
                    $pairs[] = ['parent' => (int) $parent, 'child' => (int) $component, 'type' => 'sospechado'];
                }
            }
        }

        return $pairs;
    }
}
