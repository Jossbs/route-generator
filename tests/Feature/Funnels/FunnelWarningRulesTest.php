<?php

declare(strict_types=1);

use App\Models\CatalogRule;
use App\Models\Course;
use App\Services\Funnels\FunnelRouteValidator;
use Tests\Support\FunnelFactory;

/*
 * Las advertencias de MODULO_EMBUDOS.md §6.2: no impiden publicar, pero exigen que
 * alguien las confirme.
 *
 * La diferencia con un bloqueante no es de gravedad sino de certeza. Un traslape
 * confirmado se bloquea; uno sospechado se advierte, porque afirmar una relación
 * que el catálogo no publica sería inventarla.
 */

it('advierte del traslape sospechado de Lean sobre sus cursos', function () {
    // INC-10: Lean Manufacturing declara 40 h que se parecen mucho a la suma de
    // los ocho cursos que históricamente listaba. Sospecha abierta, sin confirmar.
    $result = app(FunnelRouteValidator::class)->validate(
        FunnelFactory::route(['lean-manufacturing', 'toyota-production-system', 'smed'], 'lean'),
    );

    expect($result->codigosDeAdvertencia())->toContain('LEAN_OVERLAP_SUSPECTED');
    expect($result->publicable())->toBeTrue();
});

it('no advierte de Lean cuando el curso que lo sospecha no está en la ruta', function () {
    $result = app(FunnelRouteValidator::class)->validate(
        FunnelFactory::route(['toyota-production-system', 'smed', 'metodologia-kanban'], 'lean'),
    );

    expect($result->codigosDeAdvertencia())->not->toContain('LEAN_OVERLAP_SUSPECTED');
});

it('advierte del traslape sospechado de Kaizen sobre 5S y OEE', function () {
    // INC-11, misma naturaleza.
    $result = app(FunnelRouteValidator::class)->validate(
        FunnelFactory::route(['kaizen-mejora-continua', 'metodologia-5s'], 'kaizen'),
    );

    expect($result->codigosDeAdvertencia())->toContain('KAIZEN_OVERLAP_SUSPECTED');
});

it('advierte del salto en una cadena de prerrequisito recomendada', function () {
    // SPC antes que las Siete Herramientas: se puede, pero se avisa. El lead pudo
    // traer el fundamento aprendido, así que no es bloqueo.
    $result = app(FunnelRouteValidator::class)->validate(
        FunnelFactory::route(['spc', 'siete-herramientas-de-la-calidad'], 'calidad'),
    );

    expect($result->codigosDeAdvertencia())->toContain('PREREQUISITE_SUGGESTED');
    expect($result->publicable())->toBeTrue();
});

it('no advierte cuando la cadena recomendada va en orden', function () {
    $result = app(FunnelRouteValidator::class)->validate(
        FunnelFactory::route(['siete-herramientas-de-la-calidad', 'spc'], 'calidad'),
    );

    expect($result->codigosDeAdvertencia())->not->toContain('PREREQUISITE_SUGGESTED');
});

it('bloquea, no advierte, el salto en una cadena de nivel', function () {
    // Avanzado antes que Básico no es discutible: LEVEL_SEQUENCE lo bloquea, y la
    // advertencia de prerrequisito no lo degrada.
    $result = app(FunnelRouteValidator::class)->validate(
        FunnelFactory::route(['excel-avanzado', 'excel-basico'], 'excel'),
    );

    expect(array_column($result->bloqueantes, 'codigo'))->toContain('LEVEL_SEQUENCE');
});

it('advierte de los micro-cursos de empleabilidad dentro de una recomendación', function () {
    // Decisión pendiente §11.1: advertencia mientras no se resuelva si un embudo
    // puede incluirlos. docs/ARQUITECTURA.md §4 los marca como el puente de persuasión más
    // fuerte del catálogo, así que no se bloquean.
    $result = app(FunnelRouteValidator::class)->validate(
        FunnelFactory::route(['comunicacion-efectiva', 'curriculum-vitae-de-alto-impacto'], 'soft'),
    );

    expect($result->codigosDeAdvertencia())->toContain('EMPLOYABILITY_MICROCOURSE');
    expect($result->publicable())->toBeTrue();
});

it('convierte la advertencia de empleabilidad en bloqueo con un UPDATE', function () {
    // La decisión pendiente es reversible sin migración: cambiar la severidad de
    // la fila basta. Es lo que hace que esperar la respuesta no cueste nada.
    CatalogRule::where('code', 'EMPLOYABILITY_MICROCOURSE')->update(['severity' => 'blocking']);

    $result = app(FunnelRouteValidator::class)->validate(
        FunnelFactory::route(['comunicacion-efectiva', 'curriculum-vitae-de-alto-impacto'], 'soft'),
    );

    expect(array_column($result->bloqueantes, 'codigo'))->toContain('EMPLOYABILITY_MICROCOURSE');
    expect($result->publicable())->toBeFalse();
});

it('advierte de los cursos cuya duración se contradice entre fuentes', function () {
    // Kaizen y Gestión de Inventarios. La constancia DC-3 declara horas ante la
    // STPS: emitirla con el dato equivocado no es un problema de redacción.
    $result = app(FunnelRouteValidator::class)->validate(
        FunnelFactory::route(['kaizen-mejora-continua', 'foda'], 'kaizen'),
    );

    expect($result->codigosDeAdvertencia())->toContain('DURATION_IN_DISPUTE');
});

it('deja de advertir en cuanto la auditoría resuelve la duración', function () {
    // La lista no vive en la regla sino en la columna: resolver el dato es un
    // UPDATE sobre `courses` y la regla se entera sola.
    Course::whereIn('slug', ['kaizen-mejora-continua', 'gestion-de-inventarios'])
        ->update(['needs_duration_review' => false]);

    $result = app(FunnelRouteValidator::class)->validate(
        FunnelFactory::route(['kaizen-mejora-continua', 'foda'], 'kaizen'),
    );

    expect($result->codigosDeAdvertencia())->not->toContain('DURATION_IN_DISPUTE');
});

it('señala los cursos involucrados para que la interfaz pueda resaltarlos', function () {
    $result = app(FunnelRouteValidator::class)->validate(
        FunnelFactory::route(['autocad-completo', 'autocad-basico'], 'industrial'),
    );

    $finding = collect($result->bloqueantes)->firstWhere('codigo', 'PACKAGE_COMPONENT_OVERLAP');

    expect($finding['curso_ids'])->toHaveCount(2);
    expect($finding['curso_ids'])->toEqualCanonicalizing(
        Course::whereIn('slug', ['autocad-completo', 'autocad-basico'])->pluck('id')->all(),
    );
    expect($finding['mensaje'])->toBeString()->not->toBeEmpty();
});
