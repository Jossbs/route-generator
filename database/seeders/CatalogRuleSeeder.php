<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CatalogRule;
use App\Models\Course;
use App\Models\Specialization;
use App\Services\RouteComposition\Support\StudyPace;
use App\Services\RouteValidation\RuleType;
use Illuminate\Database\Seeder;

/**
 * Las ocho restricciones de dominio de docs/ARQUITECTURA.md §4, como filas.
 *
 * Cada una apunta a un tipo interpretado y lleva sus miembros en `rule_courses` o
 * `rule_specializations`, nunca dentro del JSONB: borrar un curso no debe dejar
 * una regla apuntando al vacío. Un traslape nuevo o una especialización incompleta
 * se resuelven insertando una fila, no tocando código.
 */
class CatalogRuleSeeder extends Seeder
{
    /** Los roles que constituyen una recomendación: el inventario de la membresía queda fuera. */
    private const RECOMMENDATION_ROLES = ['ancla', 'complementaria', 'refuerzo'];

    /**
     * Las ocho de §4. Son las únicas que un embudo no puede ignorar: el resto se
     * siembra dispensable (`waivable`), y cambiarlo es un UPDATE.
     */
    private const CRITICAL = [
        'SUPPLY_CHAIN_ROLE',
        'MODULE_DOUBLE_COUNT',
        'LEADERSHIP_OVERLAP',
        'CORE_TOOLS_PARTIAL',
        'NO_CERT_EQUIVALENCE',
        'ANNUAL_REQUIRES_MEMBERSHIP',
        'LEVEL_SEQUENCE',
        'NO_HEADING_MERGE',
    ];

    public function run(): void
    {
        $this->supplyChainRole();
        $this->moduleDoubleCount();
        $this->leadershipOverlap();
        $this->coreToolsPartial();
        $this->noCertEquivalence();
        $this->annualRequiresMembership();
        $this->levelSequence();
        $this->noHeadingMerge();

        // Las dos que aporta el Diccionario de Datos v1.0 y no estaban en
        // docs/ARQUITECTURA.md §4. Entran como filas, que es justamente lo que el motor de
        // reglas promete: una restricción nueva es un INSERT, no un deploy.
        $this->dmaicOverlap();
        $this->courseLayerRestriction();

        // Las que pide el módulo de embudos. Una ruta curada a mano puede juntar
        // cosas que el compositor automático nunca proponía, así que la defensa
        // no puede seguir siendo el criterio de quien arma la promoción.
        $this->packageComponentOverlap();
        $this->leanOverlapSuspected();
        $this->kaizenOverlapSuspected();
        $this->lexiconLessons();
        $this->suggestedPrerequisiteOrder();
        $this->employabilityMicrocourse();
        $this->durationInDispute();

        // La del ritmo de estudio (2026-09-22): el año que el documento promete
        // tiene que caber en las horas que el miembro dijo que tiene.
        $this->annualFitsPace();
    }

    /**
     * El itinerario anual cabe en las horas por semana del miembro.
     *
     * Los parámetros son los mismos con los que `StudyPace` corta los meses: si
     * uno cambiara sin el otro, la pantalla diría que cabe y el mes 12 cargaría
     * con lo que sobra.
     */
    private function annualFitsPace(): void
    {
        $this->upsert('ANNUAL_FITS_PACE', RuleType::PaceCapacity, [
            'applies_to_document_types' => [DocumentTypeSeeder::ANUAL],
            'waivable' => false,
            'parameters' => [
                'layers' => ['itinerario'],
                'weekly_hours_field' => 'weekly_hours',
                'months' => 12,
                'weeks_per_month' => StudyPace::WEEKS_PER_MONTH,
            ],
            'message_es' => 'Los cursos elegidos no caben en su año a las horas por semana que tiene. Quita cursos o sube las horas.',
            'source_note' => 'Decidido el 2026-09-22: las horas por semana se capturan y el documento no promete un año que no cabe.',
        ]);
    }

    /**
     * Un paquete «Completo» junto a los niveles que absorbe. Cubre los grupos
     * EXC-AUTOCAD, EXC-SOLIDWORKS y EXC-EXCEL con una sola fila: los pares salen
     * de `course_containments`, no de la regla.
     *
     * No es exclusión mutua. Excel Básico e Intermedio conviven sin problema; lo
     * que sobra es el par paquete↔componente, y `mutual_exclusion` prohibiría
     * también las convivencias legítimas.
     */
    private function packageComponentOverlap(): void
    {
        $this->upsert('PACKAGE_COMPONENT_OVERLAP', RuleType::ContainmentOverlap, [
            // Como los traslapes de liderazgo: se evalúa sobre lo recomendado. El
            // inventario del documento anual sí lista el paquete y sus niveles,
            // porque la membresía incluye los cuatro.
            'applies_to_roles' => self::RECOMMENDATION_ROLES,
            'parameters' => ['source' => 'containments'],
            'message_es' => 'La ruta recomienda un paquete «Completo» junto a los niveles que ya contiene. El lead lee dos renglones donde hay un solo programa, y las horas quedan contadas dos veces.',
            'source_note' => 'Diccionario de Datos v1.0 §7.1 y §7.3, grupos EXC-AUTOCAD, EXC-SOLIDWORKS y EXC-EXCEL. Los pares viven en course_containments.',
        ]);
    }

    /**
     * Advertencia, no bloqueo: el sitio nunca declaró que Lean Manufacturing
     * contenga a esos ocho cursos. La sospecha es fuerte —40 h que se parecen
     * mucho a la suma— pero no está confirmada, y no se inventa una relación de
     * paquete que el catálogo no publica. Por eso va en `rule_courses` y no en
     * `course_containments`.
     */
    private function leanOverlapSuspected(): void
    {
        $rule = $this->upsert('LEAN_OVERLAP_SUSPECTED', RuleType::ContainmentOverlap, [
            'severity' => 'warning',
            'applies_to_roles' => self::RECOMMENDATION_ROLES,
            'parameters' => ['source' => 'members'],
            'message_es' => 'Lean Manufacturing podría ya cubrir el contenido de los cursos Lean que lo acompañan. El traslape está sospechado y sin confirmar: revisa que la ruta no prometa dos veces lo mismo antes de publicarla.',
            'source_note' => 'Diccionario de Datos v1.0 §7.2, INC-10. Sospecha abierta, sin confirmar.',
        ]);

        $this->syncCourses($rule, ['lean-manufacturing'], 'parent');
        $this->addCourses($rule, [
            'toyota-production-system',
            'tpm',
            'metodologia-kanban',
            'value-stream-mapping',
            'ocho-desperdicios',
            'smed',
            'kaizen-mejora-continua',
            'trabajo-estandar',
        ], 'component');
    }

    /** Misma naturaleza que la anterior: Kaizen sobre 5'S y OEE. INC-11. */
    private function kaizenOverlapSuspected(): void
    {
        $rule = $this->upsert('KAIZEN_OVERLAP_SUSPECTED', RuleType::ContainmentOverlap, [
            'severity' => 'warning',
            'applies_to_roles' => self::RECOMMENDATION_ROLES,
            'parameters' => ['source' => 'members'],
            'message_es' => 'Kaizen – Mejora Continua podría ya cubrir Metodología 5\'S y OEE. El traslape está sospechado y sin confirmar: revísalo antes de publicar.',
            'source_note' => 'Diccionario de Datos v1.0 §7.2, INC-11. Sospecha abierta, sin confirmar.',
        ]);

        $this->syncCourses($rule, ['kaizen-mejora-continua'], 'parent');
        $this->addCourses($rule, ['metodologia-5s', 'oee'], 'component');
    }

    /**
     * El sistema habla de horas, no de lecciones. La palabra se cuela con
     * facilidad al redactar una promoción, y el sitio se contradice en el conteo
     * de lecciones más de una vez: prometerlas es prometer un dato que no se
     * sostiene.
     */
    private function lexiconLessons(): void
    {
        $this->upsert('LEXICON_LESSONS', RuleType::PhrasingBan, [
            'parameters' => ['scopes' => [CopySeeder::SCOPE_LEXICO]],
            'message_es' => 'El texto habla de «lecciones». El documento cuantifica horas, programas y constancias DC-3: el conteo de lecciones se contradice entre fuentes y no se imprime.',
            'source_note' => 'Diccionario de Datos v1.0 §7.5, R-04 e INC-05. Los patrones viven en banned_phrases.',
        ]);
    }

    /**
     * El orden pedagógico que orienta pero no bloquea.
     *
     * LEVEL_SEQUENCE ya bloquea sobre las cadenas `duro`. Esta mira las
     * `sugerido` —los cinturones Six Sigma y las tres progresiones recomendadas—
     * donde adelantarse es discutible, no imposible: el lead pudo traer el
     * fundamento aprendido. Va como advertencia para que quien arma la promoción
     * lo vea y decida.
     */
    private function suggestedPrerequisiteOrder(): void
    {
        $this->upsert('PREREQUISITE_SUGGESTED', RuleType::SequencePrerequisite, [
            'severity' => 'warning',
            'parameters' => [
                'layers' => ['itinerario'],
                // Sin `level_order`: la escalera de niveles ya la cubre
                // LEVEL_SEQUENCE, y repetirla duplicaría el mismo hallazgo.
                'level_order' => [],
                'enforce_prerequisites' => true,
                'prerequisite_strengths' => ['sugerido'],
            ],
            'message_es' => 'El itinerario coloca un curso antes que el que se recomienda cursar primero. No es un bloqueo —el lead pudo traer el fundamento— pero revisa que la secuencia se sostenga.',
            'source_note' => 'Diccionario de Datos v1.0 §7.4, cadenas `sugerido`. MODULO_EMBUDOS.md §6.2, código PRERREQUISITO.',
        ]);
    }

    /**
     * Los micro-cursos de empleabilidad dentro de una recomendación.
     *
     * Decisión pendiente (MODULO_EMBUDOS.md §11.1): si un embudo puede incluirlos
     * o no. Mientras no se resuelva va como advertencia, que es reversible sin
     * migración: cambiar `severity` a `blocking` es un UPDATE.
     *
     * Solo cubre los cuatro del clúster. Los otros tres micro-cursos —Mente
     * Millonaria, Liderazgo Efectivo, Estrategias de Negociación— ya quedan fuera
     * del itinerario y de forma bloqueante por COURSE_LAYER_RESTRICTION: el sitio
     * no publica su duración, y sin horas no se pueden ordenar en un mes. Eso es
     * una imposibilidad, no una preferencia, y no se degrada a advertencia.
     */
    private function employabilityMicrocourse(): void
    {
        $rule = $this->upsert('EMPLOYABILITY_MICROCOURSE', RuleType::MutualExclusion, [
            'severity' => 'warning',
            'applies_to_roles' => self::RECOMMENDATION_ROLES,
            // Cero permitidos: basta con que aparezca uno para levantar la mano.
            'parameters' => ['max_allowed' => 0],
            'message_es' => 'La ruta recomienda micro-cursos de empleabilidad. Son el puente de persuasión más fuerte del catálogo para quien busca avance profesional, pero duran pocas horas: confirma que sostienen el peso que les toca en esta promoción.',
            'source_note' => 'MODULO_EMBUDOS.md §11.1, código NO-APTO-RUTA. Decisión pendiente: advertencia mientras no se resuelva. docs/ARQUITECTURA.md §4 los marca como puente de persuasión, por eso no se bloquean.',
        ]);

        $this->syncCourses($rule, [
            'pqt-contraten',
            'como-venderte-a-las-empresas',
            'curriculum-vitae-de-alto-impacto',
            'como-hablar-en-una-entrevista-de-trabajo',
        ], 'member');
    }

    /**
     * Cursos cuya duración se contradice entre fuentes. Emitir una constancia
     * DC-3 con horas equivocadas es un problema ante la STPS, no de redacción.
     *
     * La lista no vive aquí: sale de `courses.needs_duration_review`. Marcar un
     * curso nuevo es un UPDATE y la regla se entera sola.
     */
    private function durationInDispute(): void
    {
        $this->upsert('DURATION_IN_DISPUTE', RuleType::CourseFlag, [
            'severity' => 'warning',
            'applies_to_roles' => self::RECOMMENDATION_ROLES,
            'parameters' => ['flag' => 'needs_duration_review'],
            'message_es' => 'La ruta se apoya en cursos cuya duración se contradice entre fuentes. Verifica las horas antes de publicar: la constancia DC-3 las declara ante la STPS.',
            'source_note' => 'Diccionario de Datos v1.0 §5, bandera dc3_validar. MODULO_EMBUDOS.md §6.2, código DURACION-EN-DISPUTA.',
        ]);
    }

    /** Restricción 1: Cadena de Suministros no puede ser ancla ni complementaria. */
    private function supplyChainRole(): void
    {
        $rule = $this->upsert('SUPPLY_CHAIN_ROLE', RuleType::RoleRestriction, [
            'parameters' => [
                // Traduce el par capa:rol del renglón a la clave de `allowed_roles`.
                'role_map' => [
                    'itinerario:ancla' => 'ancla',
                    'itinerario:complementaria' => 'complementaria',
                    'itinerario:refuerzo' => 'bloque_itinerario',
                    'itinerario:incluido' => 'bloque_itinerario',
                    'inventario:incluido' => 'inventario',
                ],
            ],
            'message_es' => 'Cadena de Suministros tiene un solo curso. Conserva su encabezado propio en el itinerario y aparece en el inventario, pero no puede ocupar rol de ancla ni de complementaria: quedaría un bloque de un curso junto a otros de ocho o dieciséis.',
            'source_note' => 'docs/ARQUITECTURA.md §4.1. La lista blanca vive en specializations.allowed_roles.',
        ]);

        $this->syncSpecializations($rule, ['suministros'], 'subject');
    }

    /** Restricción 2: 64 cursos = 55 programas. El conteo debe cuadrar. */
    private function moduleDoubleCount(): void
    {
        $this->upsert('MODULE_DOUBLE_COUNT', RuleType::DistinctContentCount, [
            'parameters' => [
                // Sin `layers`: se cuenta sobre toda la ruta, deduplicando por curso.
                'layers' => null,
            ],
            'message_es' => 'El conteo declarado no cuadra con los cursos de la ruta. Los paquetes «Completo» de Excel, AutoCAD y SolidWorks contienen a sus propios niveles: cuentan como varios cursos pero como un solo programa de contenido.',
            'source_note' => 'docs/ARQUITECTURA.md §4.2. El catálogo publica 64 cursos y 55 programas distintos (64 − 9 niveles absorbidos).',
        ]);
    }

    /** Restricción 3: los tres cursos de liderazgo se traslapan. */
    private function leadershipOverlap(): void
    {
        $rule = $this->upsert('LEADERSHIP_OVERLAP', RuleType::MutualExclusion, [
            // Se evalúa sobre la capa de itinerario, no sobre el inventario: el
            // documento anual puede listar los tres como incluidos.
            'applies_to_roles' => ['ancla', 'complementaria', 'refuerzo'],
            'parameters' => ['max_allowed' => 1],
            'message_es' => 'Liderazgo para Ingenieros, Liderazgo para Líderes y Supervisores y Liderazgo Efectivo se traslapan en contenido. Solo se recomienda uno: el que corresponda al puesto del lead.',
            'source_note' => 'docs/ARQUITECTURA.md §4.3.',
        ]);

        $this->syncCourses($rule, [
            'liderazgo-para-ingenieros',
            'liderazgo-para-lideres-y-supervisores',
            'liderazgo-efectivo',
        ], 'member');
    }

    /** Restricción 4: Core Tools está incompleto. */
    private function coreToolsPartial(): void
    {
        $rule = $this->upsert('CORE_TOOLS_PARTIAL', RuleType::CompletenessClaim, [
            'parameters' => [
                // `{name}` se sustituye por el nombre de la especialización parcial.
                'claim_patterns' => [
                    '/\b{name}\s+(completo|completa|completos|completas|integral|integrales)\b/iu',
                    '/\b{name}\s+al\s+100\s*%/iu',
                    // Reclamar las cinco, no mencionarlas: «Cuatro de las cinco
                    // herramientas AIAG» es fraseo honesto y debe pasar.
                    '/\b(incluye|cubre|abarca|domina|dominas|te\s+llevas|con)\s+(las\s+)?(cinco|5)\s+herramientas\b/iu',
                    '/\btodas\s+las\s+herramientas\s+(aiag|core\s+tools)\b/iu',
                    '/\bcore\s+tools\s+(completo|completas|integral)\b/iu',
                ],
            ],
            'message_es' => 'Core Tools publica AMEF, PPAP, SPC y Plan de Control: le faltan APQP y MSA, dos de las cinco herramientas AIAG. Ningún texto puede prometerla completa, y menos a un lead de cadena de suministro automotriz.',
            'source_note' => 'docs/ARQUITECTURA.md §4.4. Verificado en el sitio el 2026-09-08.',
        ]);

        $this->syncSpecializations($rule, ['coretools'], 'subject');
    }

    /** Restricción 5: sin equivalencias de certificación ni anclas de precio. */
    private function noCertEquivalence(): void
    {
        $this->upsert('NO_CERT_EQUIVALENCE', RuleType::PhrasingBan, [
            'parameters' => ['scopes' => [CopySeeder::SCOPE_CERTIFICACION, CopySeeder::SCOPE_PRECIO]],
            'message_es' => 'El texto afirma equivalencia con una certificación de mercado o imprime un ancla de precio. Usa fraseo de cumplimiento y cuantifica horas, programas y constancias DC-3; el precio lo dice el asesor en la conversación, donde puede manejar objeciones.',
            'source_note' => 'docs/ARQUITECTURA.md §4.5. Los patrones viven en banned_phrases.',
        ]);
    }

    /** Restricción 6: el documento anual exige datos de membresía. */
    private function annualRequiresMembership(): void
    {
        $this->upsert('ANNUAL_REQUIRES_MEMBERSHIP', RuleType::RequiredFields, [
            'applies_to_document_types' => [DocumentTypeSeeder::ANUAL],
            'parameters' => [
                'source' => 'membership',
                'fields' => ['full_name', 'occupation', 'starts_on', 'ends_on'],
            ],
            'message_es' => 'La ruta anual necesita nombre completo, ocupación, fecha de inicio y fecha de fin de vigencia. Sin esos datos no se genera.',
            'source_note' => 'docs/ARQUITECTURA.md §4.6.',
        ]);
    }

    /** Restricción 7: Básico nunca cae después de Intermedio o Avanzado. */
    private function levelSequence(): void
    {
        $this->upsert('LEVEL_SEQUENCE', RuleType::SequencePrerequisite, [
            'parameters' => [
                'layers' => ['itinerario'],
                // `completo` y `unico` quedan fuera a propósito: no son escalones.
                'level_order' => ['basico', 'intermedio', 'avanzado'],
                'group_by' => 'content_unit',
                'enforce_prerequisites' => true,
                'prerequisite_strengths' => ['duro'],
            ],
            'message_es' => 'El itinerario coloca un nivel antes que el que lo precede. Básico nunca puede caer después de Intermedio o Avanzado.',
            'source_note' => 'docs/ARQUITECTURA.md §4.7. El orden sale de courses.level y course_prerequisites.',
        ]);
    }

    /** Restricción 8: sin mezcla de encabezados. */
    private function noHeadingMerge(): void
    {
        $this->upsert('NO_HEADING_MERGE', RuleType::PresentationIsolation, [
            'parameters' => ['max_specializations_per_heading' => 1],
            'message_es' => 'Cada especialización se presenta bajo su propio título. Dos especializaciones pueden compartir un mes, jamás un encabezado.',
            'source_note' => 'docs/ARQUITECTURA.md §4.8.',
        ]);
    }

    /**
     * DMAIC es el ciclo que Green Belt y Black Belt desarrollan íntegro. Los tres
     * en una misma recomendación se leen como relleno, igual que los liderazgos.
     * Se resuelve como exclusión y no como contención porque el sitio no declara
     * que un curso contenga al otro, y no se inventa una relación que no publica.
     */
    private function dmaicOverlap(): void
    {
        $rule = $this->upsert('DMAIC_OVERLAP', RuleType::MutualExclusion, [
            // Igual que LEADERSHIP_OVERLAP: se evalúa sobre lo recomendado. El
            // inventario de la membresía sí puede listar los tres.
            'applies_to_roles' => ['ancla', 'complementaria', 'refuerzo'],
            'parameters' => ['max_allowed' => 1],
            'message_es' => 'DMAIC se traslapa con Green Belt y Black Belt, que desarrollan el ciclo completo. Solo se recomienda uno: el cinturón si el lead busca certificarse, DMAIC suelto si busca el método.',
            'source_note' => 'Diccionario de Datos v1.0 §7.3, grupo EXC-DMAIC.',
        ]);

        $this->syncCourses($rule, ['dmaic', 'green-belt-six-sigma', 'black-belt-six-sigma'], 'member');
    }

    /**
     * Tres micro-cursos no publican lecciones ni horas. Sin duración no se pueden
     * ordenar en un mes ni sostener el balanceo del itinerario, así que quedan en
     * el inventario de la membresía. La lista blanca vive en
     * `courses.allowed_layers`: moverlos de capa es un UPDATE, no un deploy.
     *
     * No se declaran sujetos: la regla vale para todo curso de la ruta, de modo
     * que restringir uno nuevo tampoco toca esta fila.
     */
    private function courseLayerRestriction(): void
    {
        $this->upsert('COURSE_LAYER_RESTRICTION', RuleType::RoleRestriction, [
            'parameters' => ['check_course_layers' => true],
            'message_es' => 'La ruta coloca un curso en una capa que su ficha no permite. Los cursos sin duración publicada aparecen en el inventario de la membresía, nunca en el itinerario recomendado.',
            'source_note' => 'Diccionario de Datos v1.0 §5, bandera no_ruta, e INC-02. Alcance acotado a los tres cursos sin horas: el clúster de empleabilidad sí entra al itinerario, por docs/ARQUITECTURA.md §4.',
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function upsert(string $code, RuleType $type, array $attributes): CatalogRule
    {
        return CatalogRule::updateOrCreate(
            ['code' => $code],
            [
                'type' => $type->value,
                'severity' => $attributes['severity'] ?? 'blocking',
                'waivable' => $attributes['waivable'] ?? ! in_array($code, self::CRITICAL, true),
                'applies_to_document_types' => $attributes['applies_to_document_types'] ?? null,
                'applies_to_roles' => $attributes['applies_to_roles'] ?? null,
                'parameters' => $attributes['parameters'] ?? null,
                'message_es' => $attributes['message_es'],
                'source_note' => $attributes['source_note'] ?? null,
                'is_active' => true,
                'effective_from' => null,
            ],
        );
    }

    /** @param list<string> $slugs */
    private function syncCourses(CatalogRule $rule, array $slugs, string $role): void
    {
        $ids = Course::whereIn('slug', $slugs)->pluck('id', 'slug');

        $this->assertResolved($rule->code, $slugs, $ids->keys()->map(strval(...))->all());

        $rule->courses()->sync($ids->mapWithKeys(fn (int $id): array => [$id => ['role' => $role]])->all());
    }

    /**
     * Agrega miembros con otro rol sin borrar los ya sincronizados. Los pares
     * paquete → componente necesitan dos roles distintos sobre la misma regla.
     *
     * @param  list<string>  $slugs
     */
    private function addCourses(CatalogRule $rule, array $slugs, string $role): void
    {
        $ids = Course::whereIn('slug', $slugs)->pluck('id', 'slug');

        $this->assertResolved($rule->code, $slugs, $ids->keys()->map(strval(...))->all());

        $rule->courses()->syncWithoutDetaching(
            $ids->mapWithKeys(fn (int $id): array => [$id => ['role' => $role]])->all(),
        );
    }

    /** @param list<string> $slugs */
    private function syncSpecializations(CatalogRule $rule, array $slugs, string $role): void
    {
        $ids = Specialization::whereIn('slug', $slugs)->pluck('id', 'slug');

        $this->assertResolved($rule->code, $slugs, $ids->keys()->map(strval(...))->all());

        $rule->specializations()->sync($ids->mapWithKeys(fn (int $id): array => [$id => ['role' => $role]])->all());
    }

    /**
     * Una regla sembrada a medias es peor que ninguna: dejaría de bloquear sin avisar.
     *
     * @param  list<string>  $expected
     * @param  array<int, string>  $found
     */
    private function assertResolved(string $ruleCode, array $expected, array $found): void
    {
        $missing = array_diff($expected, $found);

        if ($missing !== []) {
            throw new \RuntimeException(
                "La regla [{$ruleCode}] referencia slugs que no existen en el catálogo: ".implode(', ', $missing),
            );
        }
    }
}
