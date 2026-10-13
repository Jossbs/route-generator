<?php

declare(strict_types=1);

/**
 * La capa de análisis del catálogo. Procedencia [ANÁLISIS]: juicio metodológico
 * documentado y reproducible, nunca un dato que el sitio publique.
 * Fuente: Diccionario_Datos_DPSyS_v1.0.md §3 y §7.
 *
 * `technical` y `scope` son los criterios C3 y C4 de la rúbrica de complejidad,
 * los dos que no se derivan de horas ni lecciones:
 *
 *   C3 · Prerrequisito técnico
 *     1 ninguno · 2 conceptual, conocer el proceso · 3 estadística descriptiva o
 *     software especializado · 4 estadística inferencial, capacidad de proceso o
 *     simulación
 *
 *   C4 · Alcance de aplicación
 *     1 individual · 2 estación o célula · 3 área o planta · 4 organización o
 *     cadena de valor
 *
 * C1 y C2 no se transcriben aquí: los deriva `ComplexityRubric` de las horas y
 * lecciones publicadas, para que mover un umbral recalcule los 64 cursos.
 *
 * Banderas:
 *   `overlap_review`   traslape sospechado y sin confirmar. No aplica exclusión
 *                      automática: el curso entra a rutas con la marca puesta.
 *   `duration_review`  las fuentes se contradicen en la duración. Verificar antes
 *                      de emitir la constancia DC-3.
 *   `layers`           lista blanca de capas del curso; ausente = todas.
 *
 * @return array<string, array{technical: int, scope: int, overlap_review?: bool, duration_review?: bool, layers?: list<string>, note?: string}>
 */
return [
    // Lean Manufacturing
    'lean-manufacturing' => ['technical' => 2, 'scope' => 4, 'overlap_review' => true,
        'note' => 'Sus 40 h podrían contener a los otros ocho cursos Lean (Diccionario §7.2, INC-10). Sin confirmar: no se declaró una contención que el sitio no publica.'],
    'ocho-desperdicios' => ['technical' => 1, 'scope' => 2],
    'value-stream-mapping' => ['technical' => 2, 'scope' => 4],
    'toyota-production-system' => ['technical' => 2, 'scope' => 4],
    'balanceo-de-lineas' => ['technical' => 2, 'scope' => 3],
    'tpm' => ['technical' => 2, 'scope' => 3],
    'trabajo-estandar' => ['technical' => 2, 'scope' => 2],
    'metodologia-kanban' => ['technical' => 2, 'scope' => 3],
    'smed' => ['technical' => 2, 'scope' => 2],
    'lean-thinking' => ['technical' => 1, 'scope' => 3],
    'historia-economia-manufactura' => ['technical' => 1, 'scope' => 1],

    // Six Sigma
    'white-belt-six-sigma' => ['technical' => 1, 'scope' => 1],
    'yellow-belt-six-sigma' => ['technical' => 3, 'scope' => 2],
    'green-belt-six-sigma' => ['technical' => 4, 'scope' => 3],
    'black-belt-six-sigma' => ['technical' => 4, 'scope' => 4],
    'dmaic' => ['technical' => 2, 'scope' => 3, 'overlap_review' => true,
        'note' => 'Green Belt y Black Belt desarrollan el ciclo DMAIC íntegro. Se resuelve como exclusión mutua sembrada (DMAIC_OVERLAP), no como contención.'],

    // Soft Skills
    'liderazgo-para-ingenieros' => ['technical' => 1, 'scope' => 3],
    'integracion-y-trabajo-en-equipo' => ['technical' => 1, 'scope' => 2],
    'comunicacion-efectiva' => ['technical' => 1, 'scope' => 2],
    'manejo-del-personal' => ['technical' => 1, 'scope' => 3],
    'inteligencia-emocional' => ['technical' => 1, 'scope' => 1],
    'el-arte-de-la-persuasion' => ['technical' => 1, 'scope' => 1],
    'liderazgo-para-lideres-y-supervisores' => ['technical' => 1, 'scope' => 3],
    'experto-en-ventas' => ['technical' => 1, 'scope' => 2],
    'nom-035' => ['technical' => 2, 'scope' => 4],
    'pqt-contraten' => ['technical' => 1, 'scope' => 1],
    'como-venderte-a-las-empresas' => ['technical' => 1, 'scope' => 1],
    'curriculum-vitae-de-alto-impacto' => ['technical' => 1, 'scope' => 1],
    'como-hablar-en-una-entrevista-de-trabajo' => ['technical' => 1, 'scope' => 1],
    'mente-millonaria' => ['technical' => 1, 'scope' => 1, 'layers' => ['inventario'],
        'note' => 'El sitio no publica lecciones ni horas: sin duración no se puede ordenar en un mes ni sostener el balanceo del itinerario. Queda en el inventario de la membresía.'],
    'liderazgo-efectivo' => ['technical' => 1, 'scope' => 1, 'layers' => ['inventario'],
        'note' => 'Mismo caso que Mente Millonaria. Además pertenece al grupo de traslape de liderazgo, donde solo se recomienda uno.'],
    'estrategias-de-negociacion' => ['technical' => 1, 'scope' => 1, 'layers' => ['inventario'],
        'note' => 'Mismo caso que Mente Millonaria.'],

    // Ingeniería de Calidad
    'siete-herramientas-de-la-calidad' => ['technical' => 3, 'scope' => 3],
    'sistema-andon' => ['technical' => 2, 'scope' => 2],
    'poka-yoke' => ['technical' => 2, 'scope' => 2],
    'metodologia-scrum' => ['technical' => 2, 'scope' => 3],

    // Kaizen y Mejora Continua
    'metodologia-5s' => ['technical' => 1, 'scope' => 2],
    'foda' => ['technical' => 1, 'scope' => 4],
    'kaizen-mejora-continua' => ['technical' => 2, 'scope' => 3, 'overlap_review' => true, 'duration_review' => true,
        'note' => 'Podría contener 5\'S y OEE (INC-11). Además, las fuentes discrepan entre 16 h y 24 h.'],
    'oee' => ['technical' => 2, 'scope' => 3],

    // Cadena de Suministros
    'gestion-de-inventarios' => ['technical' => 3, 'scope' => 4, 'duration_review' => true,
        'note' => 'Las fuentes discrepan entre 12 h y 16 h.'],

    // Solución de Problemas
    'solucion-de-problemas-para-ingenieros' => ['technical' => 3, 'scope' => 3],
    'hoshin-kanri' => ['technical' => 2, 'scope' => 4],
    'solucion-de-problemas-8d' => ['technical' => 2, 'scope' => 3],

    // Diseño Industrial
    'autocad-completo' => ['technical' => 3, 'scope' => 2],
    'autocad-basico' => ['technical' => 3, 'scope' => 1],
    'autocad-intermedio' => ['technical' => 3, 'scope' => 1],
    'autocad-avanzado' => ['technical' => 3, 'scope' => 2],
    'solidworks-completo' => ['technical' => 3, 'scope' => 2],
    'solidworks-basico' => ['technical' => 3, 'scope' => 1],
    'solidworks-intermedio' => ['technical' => 3, 'scope' => 1],
    'solidworks-avanzado' => ['technical' => 4, 'scope' => 2],

    // Microsoft Excel
    'excel-basico' => ['technical' => 1, 'scope' => 1],
    'excel-intermedio' => ['technical' => 3, 'scope' => 1],
    'excel-avanzado' => ['technical' => 3, 'scope' => 2],
    'excel-completo' => ['technical' => 3, 'scope' => 2],

    // Core Tools
    'amef' => ['technical' => 3, 'scope' => 3],
    'ppap' => ['technical' => 3, 'scope' => 4],
    'spc' => ['technical' => 4, 'scope' => 3],
    'plan-de-control' => ['technical' => 3, 'scope' => 3],

    // Normas ISO
    'iso-9001-2015-auditor-interno' => ['technical' => 3, 'scope' => 4],
    'iso-19011-2018-auditorias' => ['technical' => 3, 'scope' => 4],

    // Análisis de Datos
    'sql-server' => ['technical' => 3, 'scope' => 2],
    'power-bi' => ['technical' => 3, 'scope' => 3],

];
