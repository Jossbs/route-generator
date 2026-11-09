<?php

declare(strict_types=1);

use App\Models\Course;
use App\Services\Funnels\FunnelRouteValidator;
use Tests\Support\FunnelFactory;

/*
 * Las restricciones que impiden publicar un embudo.
 *
 * Cada una corresponde a un renglón de MODULO_EMBUDOS.md §6.1 y se evalúa contra
 * el catálogo real sembrado: una regla que solo falla con datos de fábrica no
 * demuestra nada sobre el catálogo de DP SyS.
 *
 * El validador del embudo no conoce ninguna de estas restricciones. Reutiliza el
 * motor de reglas del catálogo, así que lo que se prueba aquí es que una ruta
 * curada a mano queda sujeta a las mismas restricciones que una compuesta por el
 * motor.
 */

function validateFunnel(array $courseSlugs, ?string $mainSlug = null, array $route = [], array $funnel = [])
{
    return app(FunnelRouteValidator::class)->validate(
        FunnelFactory::route($courseSlugs, $mainSlug, $route, $funnel),
    );
}

function codes(array $findings): array
{
    return array_column($findings, 'codigo');
}

it('deja publicar una ruta que no rompe nada', function () {
    $result = validateFunnel(['siete-herramientas-de-la-calidad', 'poka-yoke', 'spc'], 'calidad');

    expect($result->bloqueantes)->toBeEmpty();
    expect($result->publicable())->toBeTrue();
});

// §6.1 EXC-LIDERAZGO
it('bloquea dos cursos de liderazgo que se traslapan', function () {
    $result = validateFunnel(
        ['liderazgo-para-ingenieros', 'liderazgo-para-lideres-y-supervisores', 'comunicacion-efectiva'],
        'soft',
    );

    expect(codes($result->bloqueantes))->toContain('LEADERSHIP_OVERLAP');
    expect($result->publicable())->toBeFalse();
});

// §6.1 EXC-AUTOCAD, EXC-SOLIDWORKS, EXC-EXCEL
it('bloquea un paquete junto a los niveles que ya contiene', function (string $package, string $component) {
    $result = validateFunnel([$package, $component], 'industrial');

    expect(codes($result->bloqueantes))->toContain('PACKAGE_COMPONENT_OVERLAP');
})->with([
    'AutoCAD' => ['autocad-completo', 'autocad-intermedio'],
    'SolidWorks' => ['solidworks-completo', 'solidworks-basico'],
    'Excel' => ['excel-completo', 'excel-avanzado'],
]);

it('deja convivir dos niveles del mismo paquete cuando el paquete no está', function () {
    // No es exclusión mutua: lo que sobra es el par paquete↔componente. Básico e
    // Intermedio juntos son una progresión legítima.
    $result = validateFunnel(['excel-basico', 'excel-intermedio'], 'excel');

    expect(codes($result->bloqueantes))->not->toContain('PACKAGE_COMPONENT_OVERLAP');
});

// §6.1 EXC-DMAIC
it('bloquea DMAIC junto a un cinturón que desarrolla el ciclo completo', function () {
    $result = validateFunnel(['dmaic', 'green-belt-six-sigma'], 'sixsigma');

    expect(codes($result->bloqueantes))->toContain('DMAIC_OVERLAP');
});

// §6.1 SCM-STANDALONE
it('bloquea Cadena de Suministros como única especialidad de la ruta', function () {
    // Tiene un solo curso: presentarla sola deja un bloque de uno donde el lead
    // espera una especialidad.
    $result = validateFunnel(['gestion-de-inventarios'], 'suministros');

    expect(codes($result->bloqueantes))->toContain('SUPPLY_CHAIN_ROLE');
});

it('deja a Cadena de Suministros acompañar bajo su propio título', function () {
    // Puede compartir la ruta con Lean o Kaizen, cada una bajo su encabezado.
    $result = validateFunnel(
        ['lean-manufacturing', 'gestion-de-inventarios'],
        'lean',
    );

    expect(codes($result->bloqueantes))->not->toContain('SUPPLY_CHAIN_ROLE');
});

// §6.1 CORE-TOOLS-INCOMPLETO
it('bloquea el copy que promete Core Tools completo', function () {
    $result = validateFunnel(
        ['amef', 'ppap', 'spc', 'plan-de-control'],
        'coretools',
        route: ['intro_text' => 'Domina las cinco herramientas de la industria automotriz.'],
    );

    expect(codes($result->bloqueantes))->toContain('CORE_TOOLS_PARTIAL');
});

it('deja pasar el fraseo honesto sobre Core Tools', function () {
    // Cuatro de las cinco es cierto: faltan APQP y MSA.
    $result = validateFunnel(
        ['amef', 'ppap', 'spc', 'plan-de-control'],
        'coretools',
        route: ['intro_text' => 'Cuatro de las cinco herramientas AIAG, con constancia DC-3 en cada una.'],
    );

    expect(codes($result->bloqueantes))->not->toContain('CORE_TOOLS_PARTIAL');
});

// §6.1 LEXICO-LECCIONES
it('bloquea la palabra «lecciones» en cualquier campo que vea el lead', function (array $attributes) {
    $result = validateFunnel(['spc', 'amef'], 'calidad', ...$attributes);

    expect(codes($result->bloqueantes))->toContain('LEXICON_LESSONS');
})->with([
    'texto de intro' => [['route' => ['intro_text' => 'Son 24 lecciones que puedes ver a tu ritmo.']]],
    'headline del embudo' => [['funnel' => ['headline' => 'Doce lecciones para certificarte']]],
    'texto de cierre' => [['route' => ['closing_text' => 'Cada lección incluye su ejercicio.']]],
]);

it('deja pasar el mismo texto contado en horas', function () {
    $result = validateFunnel(
        ['spc', 'amef'],
        'calidad',
        route: ['intro_text' => 'Son 32 horas que puedes cursar a tu ritmo, con constancia DC-3 al terminar.'],
    );

    expect(codes($result->bloqueantes))->not->toContain('LEXICON_LESSONS');
});

// El copy del embudo sigue sujeto a las restricciones de fraseo del catálogo.
it('bloquea la comparación de precio contra una certificación de mercado', function () {
    $result = validateFunnel(
        ['green-belt-six-sigma'],
        'sixsigma',
        funnel: ['subheadline' => 'Cuatro veces más barato que un Green Belt de mercado'],
    );

    expect(codes($result->bloqueantes))->toContain('NO_CERT_EQUIVALENCE');
});

it('bloquea un curso que su ficha no deja entrar al itinerario', function () {
    // La restricción sigue vigente si Contenido limita un curso al inventario.
    Course::where('slug', 'mente-millonaria')->update(['allowed_layers' => ['inventario']]);
    $result = validateFunnel(['mente-millonaria', 'comunicacion-efectiva'], 'soft');

    expect(codes($result->bloqueantes))->toContain('COURSE_LAYER_RESTRICTION');
});
