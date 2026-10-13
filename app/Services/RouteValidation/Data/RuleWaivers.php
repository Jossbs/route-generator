<?php

declare(strict_types=1);

namespace App\Services\RouteValidation\Data;

/**
 * Lo que un embudo decidió ignorar, y hasta dónde llega esa decisión.
 *
 * La dispensa es por código de regla, pero se acota a los cursos que el embudo
 * curó: si al emitir el documento el motor o el asesor suman un curso que entra
 * en el mismo conflicto, ese fallo ya no es el que alguien revisó y vuelve a
 * bloquear. Y solo alcanza a reglas marcadas `waivable`: las ocho de §4 no se
 * dispensan aunque el código viaje aquí.
 */
final readonly class RuleWaivers
{
    /**
     * @param  list<string>  $codes
     * @param  list<int>  $scopeCourseIds  Los cursos del embudo.
     */
    public function __construct(
        public array $codes,
        public array $scopeCourseIds,
    ) {}

    public function covers(RuleEvaluation $evaluation): bool
    {
        return $evaluation->failed()
            && $evaluation->waivable
            && in_array($evaluation->ruleCode, $this->codes, true)
            && array_diff($evaluation->courseIds(), $this->scopeCourseIds) === [];
    }
}
