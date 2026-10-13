<?php

declare(strict_types=1);

/**
 * Ofertas vigentes al 2026-09-22 — fuente: las piezas de «Membresía Profesional»
 * que publica DP SyS en redes (ruta-*.png, post-iso-calidad, ISO+Calidad USD).
 *
 * Cada oferta es un embudo: su nombre y sus cursos en el orden numerado de la
 * pieza. Solo se transcribe la lista de cursos; el precio de la pieza no entra
 * (§4.5) y las horas por curso que declara la pieza no sustituyen las del
 * catálogo del sitio.
 *
 * @return list<array{name: string, courses: list<string>}>
 */
return [
    [
        'name' => 'Six Sigma',
        'courses' => [
            'white-belt-six-sigma',
            'yellow-belt-six-sigma',
            'green-belt-six-sigma',
            'black-belt-six-sigma',
        ],
    ],
    [
        'name' => 'Core Tools',
        'courses' => [
            'amef',
            'spc',
            'plan-de-control',
            'ppap',
            'metodologia-5s',
            'kaizen-mejora-continua',
            'oee',
            'foda',
        ],
    ],
    [
        'name' => 'Diseño Industrial',
        'courses' => [
            'autocad-completo',
            'solidworks-completo',
            'siete-herramientas-de-la-calidad',
            'amef',
            'plan-de-control',
            'lean-manufacturing',
            'metodologia-scrum',
            'liderazgo-para-ingenieros',
        ],
    ],
    [
        'name' => 'Lean y Mejora Continua',
        'courses' => [
            'lean-thinking',
            'lean-manufacturing',
            'ocho-desperdicios',
            'metodologia-5s',
            'trabajo-estandar',
            'smed',
            'kaizen-mejora-continua',
        ],
    ],
    [
        'name' => 'Manufactura y Calidad',
        'courses' => [
            'white-belt-six-sigma',
            'yellow-belt-six-sigma',
            'green-belt-six-sigma',
            'dmaic',
            'lean-thinking',
            'lean-manufacturing',
        ],
    ],
    [
        'name' => 'Power BI + Excel',
        'courses' => [
            'excel-basico',
            'excel-intermedio',
            'excel-avanzado',
            'power-bi',
            'hoshin-kanri',
        ],
    ],
    [
        'name' => 'ISO + Calidad',
        'courses' => [
            'iso-9001-2015-auditor-interno',
            'iso-19011-2018-auditorias',
            'liderazgo-para-lideres-y-supervisores',
            'amef',
            'spc',
            'plan-de-control',
            'hoshin-kanri',
            'siete-herramientas-de-la-calidad',
            'solucion-de-problemas-8d',
        ],
    ],
];
