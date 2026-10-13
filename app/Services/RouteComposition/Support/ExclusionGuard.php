<?php

declare(strict_types=1);

namespace App\Services\RouteComposition\Support;

use App\Models\CatalogRule;
use App\Services\RouteValidation\RuleType;
use Illuminate\Support\Facades\DB;

/**
 * Impide que el generador proponga dos cursos que no pueden convivir.
 *
 * Lee las mismas restricciones que evalúa el validador: los grupos de exclusión
 * mutua —los traslapes de liderazgo y de DMAIC hoy, los que se siembren mañana—,
 * las contenciones declaradas en `course_containments` y los pares paquete →
 * componente que aporten las reglas de traslape por contención. Ninguno de esos
 * nombres aparece aquí: sembrar una restricción nueva basta para que el generador
 * empiece a esquivarla.
 *
 * El validador sigue siendo la puerta. Esta guarda solo evita proponer rutas que
 * nacerían rechazadas.
 */
final class ExclusionGuard
{
    /** @var list<list<int>> Grupos de cursos mutuamente excluyentes. */
    private array $groups;

    /** @var array<int, list<int>> Curso => cursos con los que se traslapa. */
    private array $conflicts;

    public function __construct(string $documentTypeSlug)
    {
        $this->groups = $this->loadExclusionGroups($documentTypeSlug);
        $this->conflicts = $this->buildConflictMap($documentTypeSlug);
    }

    /**
     * ¿Puede este curso convivir con los ya elegidos?
     *
     * @param  list<int>  $chosenCourseIds
     */
    public function admits(int $courseId, array $chosenCourseIds): bool
    {
        foreach ($this->conflicts[$courseId] ?? [] as $conflicting) {
            if (in_array($conflicting, $chosenCourseIds, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Los grupos de exclusión que aplican a este documento, como listas de ids.
     *
     * @return list<list<int>>
     */
    public function groups(): array
    {
        return $this->groups;
    }

    /**
     * Reglas de exclusión mutua vigentes, con sus miembros.
     *
     * @return list<list<int>>
     */
    private function loadExclusionGroups(string $documentTypeSlug): array
    {
        $groups = [];

        $rules = CatalogRule::query()
            ->inEffect()
            ->where('type', RuleType::MutualExclusion->value)
            ->with('courses:id')
            ->get();

        foreach ($rules as $rule) {
            if (! $rule->appliesToDocumentType($documentTypeSlug)) {
                continue;
            }

            $ids = array_map(intval(...), array_values($rule->courses->pluck('id')->all()));

            // Un grupo de un solo miembro no excluye nada: sería una regla que no
            // puede fallar, y guardarla haría al mapa de conflictos crecer sin motivo.
            if (count($ids) > 1) {
                $groups[] = $ids;
            }
        }

        return $groups;
    }

    /**
     * Aplana grupos y contenciones a un mapa curso => conflictos.
     *
     * La contención entra aquí porque un paquete «Completo» junto a sus propios
     * niveles es la misma clase de problema: dos renglones que el lead lee como
     * dos cosas distintas cuando son una sola.
     *
     * @return array<int, list<int>>
     */
    private function buildConflictMap(string $documentTypeSlug): array
    {
        $conflicts = [];

        foreach ($this->groups as $group) {
            foreach ($group as $member) {
                foreach ($group as $other) {
                    if ($member !== $other) {
                        $conflicts[$member][] = $other;
                    }
                }
            }
        }

        foreach ($this->containmentPairs($documentTypeSlug) as [$parent, $child]) {
            $conflicts[$parent][] = $child;
            $conflicts[$child][] = $parent;
        }

        return array_map(
            fn (array $ids): array => array_values(array_unique($ids)),
            $conflicts,
        );
    }

    /**
     * Los pares paquete → componente: los confirmados en `course_containments` y
     * los que declaren las reglas de traslape por contención, que es donde viven
     * las sospechas todavía sin confirmar.
     *
     * Aquí se esquivan ambos por igual. Que una sospecha solo advierta en el
     * validador no significa que valga la pena proponerla: el asesor no debería
     * tener que decidir sobre una advertencia que el generador pudo evitar.
     *
     * @return list<array{int, int}>
     */
    private function containmentPairs(string $documentTypeSlug): array
    {
        $pairs = [];

        foreach (DB::table('course_containments')->get(['parent_course_id', 'child_course_id']) as $row) {
            $pairs[] = [(int) $row->parent_course_id, (int) $row->child_course_id];
        }

        $rules = CatalogRule::query()
            ->inEffect()
            ->where('type', RuleType::ContainmentOverlap->value)
            ->with('courses:id')
            ->get()
            ->filter(fn (CatalogRule $rule): bool => $rule->appliesToDocumentType($documentTypeSlug));

        foreach ($rules as $rule) {
            $parents = $rule->courses->where('pivot.role', 'parent')->pluck('id');
            $components = $rule->courses->where('pivot.role', 'component')->pluck('id');

            foreach ($parents as $parent) {
                foreach ($components as $component) {
                    if ($parent !== $component) {
                        $pairs[] = [(int) $parent, (int) $component];
                    }
                }
            }
        }

        return $pairs;
    }
}
