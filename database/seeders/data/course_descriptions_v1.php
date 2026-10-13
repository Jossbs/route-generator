<?php

declare(strict_types=1);

/**
 * Las 64 descripciones cortas, literales del sitio.
 * Fuente: https://academy.dpsys.com.mx/cursos, consultado 2026-09-08.
 * Transcritas en Diccionario_Datos_DPSyS_v1.0.md §6.
 *
 * Procedencia [SITIO]: es el único texto de curso que el PDF puede imprimir hoy
 * sin riesgo, porque viene textual de DP SyS. Vive aparte de catalog_v1.php para
 * no mezclarse con la capa de análisis, y aparte de `outcome_phrase` porque esa
 * columna cambiará cuando Contenido apruebe las frases de resultado: cuando eso
 * pase, este archivo sigue siendo el respaldo literal verificable.
 *
 * @return array<string, string> slug del curso => descripción
 */
return [
    // Lean Manufacturing
    'lean-manufacturing' => 'Elimina desperdicios con el sistema de producción Toyota',
    'ocho-desperdicios' => 'Identifica y elimina las mudas de tu proceso',
    'value-stream-mapping' => 'Mapea el flujo de valor y detecta cuellos de botella',
    'toyota-production-system' => 'Los pilares del sistema de producción más eficiente del mundo',
    'balanceo-de-lineas' => 'Balancea la producción al ritmo de la demanda con Takt Time y tiempo de ciclo',
    'tpm' => 'Maximiza la disponibilidad y vida útil de tus equipos',
    'trabajo-estandar' => 'Estandariza operaciones para asegurar calidad y ritmo',
    'metodologia-kanban' => 'Controla el flujo de trabajo con señales visuales',
    'smed' => 'Cambios rápidos de herramental para flexibilizar la producción',
    'lean-thinking' => 'Adopta la mentalidad de mejora y flujo de valor',
    'historia-economia-manufactura' => 'Contexto y evolución de los sistemas productivos',

    // Six Sigma
    'white-belt-six-sigma' => 'Fundamentos de la metodología Six Sigma',
    'yellow-belt-six-sigma' => 'Apoya proyectos de mejora con herramientas estadísticas',
    'green-belt-six-sigma' => 'Lidera proyectos DMAIC y reduce la variación',
    'black-belt-six-sigma' => 'Dirige proyectos DMAIC complejos y domina la estadística avanzada',
    'dmaic' => 'El ciclo Definir-Medir-Analizar-Mejorar-Controlar',

    // Soft Skills
    'liderazgo-para-ingenieros' => 'Lleva tu perfil técnico a roles de liderazgo',
    'integracion-y-trabajo-en-equipo' => 'Construye equipos cohesionados y productivos',
    'comunicacion-efectiva' => 'Transmite ideas con claridad e impacto',
    'manejo-del-personal' => 'Coordina, motiva y desarrolla a tu gente',
    'inteligencia-emocional' => 'Gestiona emociones para decidir y colaborar mejor',
    'el-arte-de-la-persuasion' => 'Influye y convence con argumentos sólidos',
    'liderazgo-para-lideres-y-supervisores' => 'Dirige equipos de planta con autoridad y empatía',
    'experto-en-ventas' => 'Técnicas de venta consultiva y cierre',
    'nom-035' => 'Identifica y previene factores de riesgo psicosocial',
    'pqt-contraten' => 'Consigue el trabajo que quieres, de principio a fin',
    'como-venderte-a-las-empresas' => 'Muestra lo que las empresas de verdad contratan',
    'curriculum-vitae-de-alto-impacto' => 'Un CV que llega a la entrevista, no al archivo',
    'como-hablar-en-una-entrevista-de-trabajo' => 'Llega preparado a la pregunta que descarta candidatos',
    'mente-millonaria' => 'Cambia tu forma de pensar el dinero',
    'liderazgo-efectivo' => 'Dirige con claridad y gana el respeto de tu equipo',
    'estrategias-de-negociacion' => 'Cierra acuerdos sin ceder de más',

    // Ingeniería de Calidad
    'siete-herramientas-de-la-calidad' => 'Analiza y resuelve problemas de calidad con datos',
    'sistema-andon' => 'Gestión visual de anomalías en tiempo real',
    'poka-yoke' => 'Diseña sistemas a prueba de errores',
    'metodologia-scrum' => 'Gestiona proyectos ágiles por sprints',

    // Kaizen y Mejora Continua
    'metodologia-5s' => 'Organiza y estandariza tu lugar de trabajo',
    'foda' => 'Diagnostica fortalezas, debilidades y oportunidades',
    'kaizen-mejora-continua' => 'Implementa mejoras incrementales día a día',
    'oee' => 'Mide la eficiencia global de tus equipos',

    // Cadena de Suministros
    'gestion-de-inventarios' => 'Optimiza niveles de stock y rotación',

    // Solución de Problemas
    'solucion-de-problemas-para-ingenieros' => 'Metodologías analíticas para causa raíz',
    'hoshin-kanri' => 'Despliega objetivos estratégicos a toda la organización',
    'solucion-de-problemas-8d' => 'Resuelve problemas de raíz con el método 8D',

    // Diseño Industrial
    'autocad-completo' => 'Diseño 2D y 3D de principio a fin',
    'autocad-basico' => 'Dibujo 2D para ingeniería',
    'autocad-intermedio' => 'Bloques, capas y acotación profesional',
    'autocad-avanzado' => 'Modelado 3D y presentación de planos',
    'solidworks-completo' => 'De básico a avanzado: modelado, ensambles y planos',
    'solidworks-basico' => 'Modelado 3D de piezas desde cero',
    'solidworks-intermedio' => 'Ensambles y relaciones de posición',
    'solidworks-avanzado' => 'Superficies, chapa metálica y simulación',

    // Microsoft Excel
    'excel-basico' => 'Hojas, fórmulas y formato esencial',
    'excel-intermedio' => 'Funciones lógicas, búsqueda y gráficos',
    'excel-avanzado' => 'Tablas dinámicas, macros y automatización',
    'excel-completo' => 'De básico a macros y dashboards',

    // Core Tools
    'amef' => 'Analiza modos y efectos de falla potenciales',
    'ppap' => 'Proceso de aprobación de partes para producción',
    'spc' => 'Controla la variación con gráficos de control',
    'plan-de-control' => 'Documenta y asegura el control del proceso',

    // Normas ISO
    'iso-9001-2015-auditor-interno' => 'Planea y ejecuta auditorías internas de calidad',
    'iso-19011-2018-auditorias' => 'Directrices para auditar sistemas de gestión',

    // Análisis de Datos
    'sql-server' => 'Consulta y administra bases de datos relacionales',
    'power-bi' => 'Dashboards ejecutivos y análisis para planta',

];
