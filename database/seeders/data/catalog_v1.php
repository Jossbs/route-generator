<?php

declare(strict_types=1);

/**
 * Catálogo v1 — fuente: https://academy.dpsys.com.mx/cursos, consultado 2026-09-08.
 * Transcrito en design/CATALOGO-SITIO.md. Nada de este archivo está inventado.
 *
 * `lessons` y `hours` en null = el sitio no publica el dato.
 * `unit` ausente = el curso es su propia unidad de contenido (1:1).
 * Las frases de resultado quedan fuera: Contenido todavía no aprueba las 64.
 *
 * @return array{specializations: list<array<string, mixed>>, content_units: list<array<string, mixed>>, containments: list<array<string, mixed>>, prerequisites: list<array<string, mixed>>, tags: list<array<string, mixed>>}
 */
return [

    /*
     * Unidades de contenido compartidas: los tres paquetes «Completo» y sus niveles
     * apuntan a una sola. De aquí sale la diferencia 64 cursos / 55 programas.
     */
    'content_units' => [
        ['slug' => 'autocad', 'name' => 'AutoCAD', 'specialization' => 'industrial'],
        ['slug' => 'solidworks', 'name' => 'SolidWorks', 'specialization' => 'industrial'],
        ['slug' => 'microsoft-excel', 'name' => 'Microsoft Excel', 'specialization' => 'excel'],
    ],

    'specializations' => [
        [
            'slug' => 'lean',
            'name' => 'Lean Manufacturing',
            'display_order' => 1,
            'notes' => 'El home del sitio la llama «Manufactura»; el listado de cursos la llama Lean Manufacturing.',
            'courses' => [
                ['slug' => 'lean-manufacturing', 'name' => 'Lean Manufacturing', 'lessons' => 12, 'hours' => 40],
                ['slug' => 'ocho-desperdicios', 'name' => 'Los 8 Desperdicios', 'lessons' => 5, 'hours' => 8],
                ['slug' => 'value-stream-mapping', 'name' => 'Value Stream Mapping', 'lessons' => 6, 'hours' => 12],
                ['slug' => 'toyota-production-system', 'name' => 'Toyota Production System', 'lessons' => 8, 'hours' => 16],
                ['slug' => 'balanceo-de-lineas', 'name' => 'Balanceo de Líneas', 'lessons' => 4, 'hours' => 6],
                ['slug' => 'tpm', 'name' => 'TPM – Mantenimiento Total Productivo', 'lessons' => 8, 'hours' => 16],
                ['slug' => 'trabajo-estandar', 'name' => 'Trabajo Estándar', 'lessons' => 4, 'hours' => 8],
                ['slug' => 'metodologia-kanban', 'name' => 'Metodología Kanban', 'lessons' => 5, 'hours' => 10],
                ['slug' => 'smed', 'name' => 'SMED', 'lessons' => 6, 'hours' => 12],
                ['slug' => 'lean-thinking', 'name' => 'Lean Thinking', 'lessons' => 8, 'hours' => 16],
                ['slug' => 'historia-economia-manufactura', 'name' => 'Historia de la Economía y Manufactura', 'lessons' => 4, 'hours' => 6],
            ],
        ],
        [
            'slug' => 'sixsigma',
            'name' => 'Six Sigma',
            'display_order' => 2,
            'courses' => [
                ['slug' => 'white-belt-six-sigma', 'name' => 'White Belt – Six Sigma', 'lessons' => 5, 'hours' => 8],
                ['slug' => 'yellow-belt-six-sigma', 'name' => 'Yellow Belt – Six Sigma', 'lessons' => 10, 'hours' => 24],
                ['slug' => 'green-belt-six-sigma', 'name' => 'Green Belt – Six Sigma', 'lessons' => 14, 'hours' => 40],
                ['slug' => 'black-belt-six-sigma', 'name' => 'Black Belt – Six Sigma', 'lessons' => 10, 'hours' => 40],
                ['slug' => 'dmaic', 'name' => 'DMAIC', 'lessons' => 8, 'hours' => 16],
            ],
        ],
        [
            'slug' => 'soft',
            'name' => 'Soft Skills',
            'display_order' => 3,
            'notes' => 'El home del sitio dice 9 cursos; el listado publica 16. Se toma el listado.',
            'courses' => [
                ['slug' => 'liderazgo-para-ingenieros', 'name' => 'Liderazgo para Ingenieros', 'lessons' => 9, 'hours' => 16],
                ['slug' => 'integracion-y-trabajo-en-equipo', 'name' => 'Integración y Trabajo en Equipo', 'lessons' => 5, 'hours' => 8],
                ['slug' => 'comunicacion-efectiva', 'name' => 'Comunicación Efectiva', 'lessons' => 7, 'hours' => 12],
                ['slug' => 'manejo-del-personal', 'name' => 'Manejo del Personal', 'lessons' => 6, 'hours' => 10],
                ['slug' => 'inteligencia-emocional', 'name' => 'Inteligencia Emocional', 'lessons' => 6, 'hours' => 10],
                ['slug' => 'el-arte-de-la-persuasion', 'name' => 'El Arte de la Persuasión', 'lessons' => 5, 'hours' => 8],
                ['slug' => 'liderazgo-para-lideres-y-supervisores', 'name' => 'Liderazgo para Líderes y Supervisores', 'lessons' => 7, 'hours' => 12],
                ['slug' => 'experto-en-ventas', 'name' => 'Experto en Ventas', 'lessons' => 8, 'hours' => 16],
                ['slug' => 'nom-035', 'name' => 'NOM-035', 'lessons' => 5, 'hours' => 8],
                ['slug' => 'pqt-contraten', 'name' => 'PQT Contraten', 'lessons' => 12, 'hours' => 9],
                ['slug' => 'como-venderte-a-las-empresas', 'name' => 'Cómo venderte a las empresas', 'lessons' => 5, 'hours' => 3],
                ['slug' => 'curriculum-vitae-de-alto-impacto', 'name' => 'Curriculum Vitae de Alto Impacto', 'lessons' => 5, 'hours' => 3],
                ['slug' => 'como-hablar-en-una-entrevista-de-trabajo', 'name' => 'Cómo hablar en una entrevista de trabajo', 'lessons' => 4, 'hours' => 3],
                ['slug' => 'mente-millonaria', 'name' => 'Mente Millonaria', 'lessons' => null, 'hours' => null],
                ['slug' => 'liderazgo-efectivo', 'name' => 'Liderazgo Efectivo', 'lessons' => null, 'hours' => null],
                ['slug' => 'estrategias-de-negociacion', 'name' => 'Estrategias de Negociación', 'lessons' => null, 'hours' => null],
            ],
        ],
        [
            'slug' => 'calidad',
            'name' => 'Ingeniería de Calidad',
            'display_order' => 4,
            'courses' => [
                ['slug' => 'siete-herramientas-de-la-calidad', 'name' => '7 Herramientas de la Calidad', 'lessons' => 8, 'hours' => 16],
                ['slug' => 'sistema-andon', 'name' => 'Sistema Andon', 'lessons' => 4, 'hours' => 6],
                ['slug' => 'poka-yoke', 'name' => 'Poka Yoke', 'lessons' => 5, 'hours' => 8],
                ['slug' => 'metodologia-scrum', 'name' => 'Metodología SCRUM', 'lessons' => 7, 'hours' => 12],
            ],
        ],
        [
            'slug' => 'kaizen',
            'name' => 'Kaizen y Mejora Continua',
            'display_order' => 5,
            'courses' => [
                ['slug' => 'metodologia-5s', 'name' => "Metodología 5'S", 'lessons' => 5, 'hours' => 8],
                ['slug' => 'foda', 'name' => 'FODA', 'lessons' => 4, 'hours' => 6],
                ['slug' => 'kaizen-mejora-continua', 'name' => 'Kaizen – Mejora Continua', 'lessons' => 8, 'hours' => 16],
                ['slug' => 'oee', 'name' => 'OEE', 'lessons' => 5, 'hours' => 8],
            ],
        ],
        [
            'slug' => 'suministros',
            'name' => 'Cadena de Suministros',
            'display_order' => 6,
            'notes' => 'Una sola especialidad con un solo curso. Conserva identidad y encabezado propio; lo que no puede es ocupar rol de ancla o complementaria (SUPPLY_CHAIN_ROLE).',
            'allowed_roles' => [
                'ancla' => false,
                'complementaria' => false,
                'bloque_itinerario' => true,
                'inventario' => true,
            ],
            'courses' => [
                ['slug' => 'gestion-de-inventarios', 'name' => 'Gestión de Inventarios', 'lessons' => 7, 'hours' => 12],
            ],
        ],
        [
            'slug' => 'problemas',
            'name' => 'Solución de Problemas',
            'display_order' => 7,
            'courses' => [
                ['slug' => 'solucion-de-problemas-para-ingenieros', 'name' => 'Solución de Problemas para Ingenieros', 'lessons' => 6, 'hours' => 12],
                ['slug' => 'hoshin-kanri', 'name' => 'Hoshin Kanri – Planeación Estratégica', 'lessons' => 7, 'hours' => 16],
                ['slug' => 'solucion-de-problemas-8d', 'name' => "Solución de Problemas 8 D's", 'lessons' => 6, 'hours' => 12],
            ],
        ],
        [
            'slug' => 'industrial',
            'name' => 'Diseño Industrial',
            'display_order' => 8,
            'courses' => [
                ['slug' => 'autocad-completo', 'name' => 'AutoCAD Completo', 'lessons' => 18, 'hours' => 36, 'level' => 'completo', 'unit' => 'autocad'],
                ['slug' => 'autocad-basico', 'name' => 'AutoCAD Básico', 'lessons' => 9, 'hours' => 16, 'level' => 'basico', 'unit' => 'autocad'],
                ['slug' => 'autocad-intermedio', 'name' => 'AutoCAD Intermedio', 'lessons' => 9, 'hours' => 16, 'level' => 'intermedio', 'unit' => 'autocad'],
                ['slug' => 'autocad-avanzado', 'name' => 'AutoCAD Avanzado', 'lessons' => 9, 'hours' => 16, 'level' => 'avanzado', 'unit' => 'autocad'],
                ['slug' => 'solidworks-completo', 'name' => 'SolidWorks Completo', 'lessons' => 16, 'hours' => 36, 'level' => 'completo', 'unit' => 'solidworks'],
                ['slug' => 'solidworks-basico', 'name' => 'SolidWorks Básico', 'lessons' => 9, 'hours' => 16, 'level' => 'basico', 'unit' => 'solidworks'],
                ['slug' => 'solidworks-intermedio', 'name' => 'SolidWorks Intermedio', 'lessons' => 9, 'hours' => 16, 'level' => 'intermedio', 'unit' => 'solidworks'],
                ['slug' => 'solidworks-avanzado', 'name' => 'SolidWorks Avanzado', 'lessons' => 9, 'hours' => 16, 'level' => 'avanzado', 'unit' => 'solidworks'],
            ],
        ],
        [
            'slug' => 'excel',
            'name' => 'Microsoft Excel',
            'display_order' => 9,
            'courses' => [
                ['slug' => 'excel-basico', 'name' => 'Microsoft Excel Básico', 'lessons' => 8, 'hours' => 12, 'level' => 'basico', 'unit' => 'microsoft-excel'],
                ['slug' => 'excel-intermedio', 'name' => 'Microsoft Excel Intermedio', 'lessons' => 9, 'hours' => 12, 'level' => 'intermedio', 'unit' => 'microsoft-excel'],
                ['slug' => 'excel-avanzado', 'name' => 'Microsoft Excel Avanzado', 'lessons' => 10, 'hours' => 12, 'level' => 'avanzado', 'unit' => 'microsoft-excel'],
                ['slug' => 'excel-completo', 'name' => 'Microsoft Excel Completo', 'lessons' => 18, 'hours' => 36, 'level' => 'completo', 'unit' => 'microsoft-excel'],
            ],
        ],
        [
            'slug' => 'coretools',
            'name' => 'Core Tools',
            'display_order' => 10,
            'completeness_status' => 'partial',
            'missing_components' => ['APQP', 'MSA'],
            'notes' => 'Publica 4 de las 5 herramientas AIAG. Faltan APQP y MSA. Ningún texto puede prometerla completa (CORE_TOOLS_PARTIAL).',
            'courses' => [
                ['slug' => 'amef', 'name' => 'AMEF', 'lessons' => 7, 'hours' => 12],
                ['slug' => 'ppap', 'name' => 'PPAP', 'lessons' => 6, 'hours' => 10],
                ['slug' => 'spc', 'name' => 'Control Estadístico de Procesos (SPC)', 'lessons' => 8, 'hours' => 16],
                ['slug' => 'plan-de-control', 'name' => 'Plan de Control', 'lessons' => 5, 'hours' => 8],
            ],
        ],
        [
            'slug' => 'iso',
            'name' => 'Normas ISO',
            'display_order' => 11,
            'courses' => [
                ['slug' => 'iso-9001-2015-auditor-interno', 'name' => 'ISO 9001:2015 Auditor Interno', 'lessons' => 9, 'hours' => 20],
                ['slug' => 'iso-19011-2018-auditorias', 'name' => 'ISO 19011:2018 Auditorías de Sistemas de Gestión', 'lessons' => 8, 'hours' => 16],
            ],
        ],
        [
            'slug' => 'datos',
            'name' => 'Análisis de Datos',
            'display_order' => 12,
            'courses' => [
                ['slug' => 'sql-server', 'name' => 'SQL Server', 'lessons' => 9, 'hours' => 16],
                ['slug' => 'power-bi', 'name' => 'Power BI', 'lessons' => 8, 'hours' => 12],
            ],
        ],
    ],

    /*
     * Contención: qué paquete «Completo» absorbe a qué nivel. Es el dato que
     * sostiene el conteo 64 cursos / 55 programas (MODULE_DOUBLE_COUNT).
     */
    'containments' => [
        [
            'parent' => 'autocad-completo',
            'children' => ['autocad-basico', 'autocad-intermedio', 'autocad-avanzado'],
            'type' => 'parcial',
            'notes' => 'El paquete declara 18 lecciones / 36 h; sus tres niveles suman 27 lecciones / 48 h. La contención es real pero no equivalente: puede ser condensación o un dato viejo del sitio. Auditoría pendiente.',
        ],
        [
            'parent' => 'solidworks-completo',
            'children' => ['solidworks-basico', 'solidworks-intermedio', 'solidworks-avanzado'],
            'type' => 'parcial',
            'notes' => 'Mismo patrón que AutoCAD: 16 lecciones / 36 h contra 27 / 48 de sus niveles. Detectado al transcribir el sitio el 2026-09-08; no estaba documentado.',
        ],
        [
            'parent' => 'excel-completo',
            'children' => ['excel-basico', 'excel-intermedio', 'excel-avanzado'],
            'type' => 'equivalente',
            'notes' => 'Las 36 h coinciden con la suma de los tres niveles; las lecciones (18 contra 26) no. Se marca equivalente por las horas.',
        ],
    ],

    /*
     * Precedencia. Los niveles son `duro` porque LEVEL_SEQUENCE bloquea sobre ellos;
     * los cinturones Six Sigma y las tres cadenas metodológicas son `sugerido`:
     * orientan al generador y advierten al validador, pero no bloquean.
     *
     * Siete cadenas en total. Solo se declaran las evidentes por nomenclatura o por
     * dependencia metodológica reconocida; no se fabrican prerrequisitos
     * «razonables».
     */
    'prerequisites' => [
        ['course' => 'autocad-intermedio', 'requires' => 'autocad-basico', 'strength' => 'duro'],
        ['course' => 'autocad-avanzado', 'requires' => 'autocad-intermedio', 'strength' => 'duro'],
        ['course' => 'solidworks-intermedio', 'requires' => 'solidworks-basico', 'strength' => 'duro'],
        ['course' => 'solidworks-avanzado', 'requires' => 'solidworks-intermedio', 'strength' => 'duro'],
        ['course' => 'excel-intermedio', 'requires' => 'excel-basico', 'strength' => 'duro'],
        ['course' => 'excel-avanzado', 'requires' => 'excel-intermedio', 'strength' => 'duro'],
        ['course' => 'yellow-belt-six-sigma', 'requires' => 'white-belt-six-sigma', 'strength' => 'sugerido'],
        ['course' => 'green-belt-six-sigma', 'requires' => 'yellow-belt-six-sigma', 'strength' => 'sugerido'],
        ['course' => 'black-belt-six-sigma', 'requires' => 'green-belt-six-sigma', 'strength' => 'sugerido'],

        // Las tres cadenas por dependencia metodológica reconocida, no por
        // nomenclatura. Orientan la secuencia y no la bloquean: el lead pudo
        // traer el fundamento aprendido de su trabajo.
        ['course' => 'spc', 'requires' => 'siete-herramientas-de-la-calidad', 'strength' => 'sugerido'],
        ['course' => 'lean-manufacturing', 'requires' => 'ocho-desperdicios', 'strength' => 'sugerido'],
        ['course' => 'kaizen-mejora-continua', 'requires' => 'metodologia-5s', 'strength' => 'sugerido'],
    ],

    /*
     * v1 solo siembra las dos etiquetas verificables. El resto llega con el Excel.
     */
    'tags' => [
        [
            'slug' => 'empleabilidad',
            'type' => 'cluster',
            'name' => 'Empleabilidad',
            'courses' => [
                'pqt-contraten',
                'como-venderte-a-las-empresas',
                'curriculum-vitae-de-alto-impacto',
                'como-hablar-en-una-entrevista-de-trabajo',
            ],
        ],
        [
            'slug' => 'automotriz',
            'type' => 'industria',
            'name' => 'Automotriz',
            'courses' => ['amef', 'ppap', 'spc', 'plan-de-control'],
        ],
    ],
];
