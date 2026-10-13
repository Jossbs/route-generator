<?php

declare(strict_types=1);

namespace App\Services\RouteValidation;

/**
 * Los tipos que el validador sabe interpretar.
 *
 * Los ocho primeros cubren las restricciones de docs/ARQUITECTURA.md §4. Los dos últimos los
 * pide el módulo de embudos: una ruta curada a mano puede juntar un paquete con
 * sus componentes o apoyarse en un curso con duración en disputa, cosas que el
 * compositor automático nunca proponía.
 *
 * En la base `catalog_rules.type` es un string y no un enum: agregar un tipo exige
 * escribir su intérprete de todos modos, así que la fuente de verdad es el registro
 * de intérpretes, no una restricción de columna.
 */
enum RuleType: string
{
    case MutualExclusion = 'mutual_exclusion';
    case RoleRestriction = 'role_restriction';
    case DistinctContentCount = 'distinct_content_count';
    case CompletenessClaim = 'completeness_claim';
    case PhrasingBan = 'phrasing_ban';
    case RequiredFields = 'required_fields';
    case SequencePrerequisite = 'sequence_prerequisite';
    case PresentationIsolation = 'presentation_isolation';
    case ContainmentOverlap = 'containment_overlap';
    case CourseFlag = 'course_flag';
    case PaceCapacity = 'pace_capacity';
}
