<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;
use Tests\Support\FunnelFactory;

/*
 * El panel del validador, en vivo.
 *
 * Evalúa lo que está en pantalla sin guardarlo, con el mismo validador y sobre el
 * mismo borrador que la emisión del documento. Un panel que evalúe distinto que la
 * emisión enseñaría a desconfiar de él.
 */

it('marca el traslape de liderazgo sin guardar nada', function () {
    $route = FunnelFactory::route(['spc'], 'calidad');

    $response = $this->actingAs(FunnelFactory::author())
        ->post("/embudos/{$route->funnel_id}/validacion", [
            'items' => [
                ['course_id' => courseId('liderazgo-para-ingenieros'), 'suggested_month' => 1],
                ['course_id' => courseId('liderazgo-para-lideres-y-supervisores'), 'suggested_month' => 2],
            ],
            'copy_text' => [],
        ]);

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('validacionEnVivo.bloqueantes.0', fn (AssertableInertia $finding) => $finding
            ->where('codigo', 'LEADERSHIP_OVERLAP')
            ->has('mensaje')
            ->has('curso_ids')
            ->etc()));

    // La ruta guardada no se movió: el panel es una lectura.
    expect($route->refresh()->orderedCourseIds())->toBe([courseId('spc')]);
});

it('descuenta los paquetes al contar horas netas', function () {
    $route = FunnelFactory::route(['spc'], 'calidad');

    $this->actingAs(FunnelFactory::author())
        ->post("/embudos/{$route->funnel_id}/validacion", [
            'funnel_route_id' => $route->id,
            'items' => [
                ['course_id' => courseId('autocad-completo'), 'suggested_month' => 1],
                ['course_id' => courseId('autocad-basico'), 'suggested_month' => 2],
            ],
            'copy_text' => [],
        ])
        ->assertInertia(fn (AssertableInertia $page) => $page
            // El paquete manda: 36 h declaradas, no la suma de sus niveles.
            ->where('validacionEnVivo.totales.horas_netas', 36)
            ->where('validacionEnVivo.totales.programas', 1));
});

it('caza la palabra prohibida en el copy antes de que llegue al lead', function () {
    $route = FunnelFactory::route(['spc'], 'calidad');

    $this->actingAs(FunnelFactory::author())
        ->post("/embudos/{$route->funnel_id}/validacion", [
            'funnel_route_id' => $route->id,
            'items' => [['course_id' => courseId('spc'), 'suggested_month' => 1]],
            'copy_text' => ['Llévate 18 lecciones de control estadístico'],
        ])
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('validacionEnVivo.bloqueantes.0.codigo', 'LEXICON_LESSONS')
            ->etc());
});

it('aprueba una composición limpia', function () {
    $route = FunnelFactory::route(['spc'], 'calidad');

    $this->actingAs(FunnelFactory::author())
        ->post("/embudos/{$route->funnel_id}/validacion", [
            'funnel_route_id' => $route->id,
            'main_specialization_id' => $route->main_specialization_id,
            'items' => [
                ['course_id' => courseId('siete-herramientas-de-la-calidad'), 'suggested_month' => 1],
                ['course_id' => courseId('spc'), 'suggested_month' => 3],
            ],
            'copy_text' => ['Constancia DC-3 oficial ante la STPS en cada curso.'],
        ])
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('validacionEnVivo.bloqueantes', 0)
            ->etc());
});

it('responde solo el veredicto cuando el editor recarga parcialmente', function () {
    // Es la petición que manda el panel con cada cambio: si trajera el catálogo
    // completo y las demás rutas, revalidar mientras alguien mueve cursos
    // costaría una lectura entera del catálogo por tecla.
    $route = FunnelFactory::route(['spc'], 'calidad');

    $response = $this->actingAs(FunnelFactory::author())
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => '1',
            'X-Inertia-Partial-Component' => 'embudos/editar',
            'X-Inertia-Partial-Data' => 'validacionEnVivo',
        ])
        ->post("/embudos/{$route->funnel_id}/validacion", [
            'funnel_route_id' => $route->id,
            'items' => [['course_id' => courseId('spc'), 'suggested_month' => 1]],
            'copy_text' => [],
        ]);

    $response->assertOk();

    $props = $response->json('props');

    expect($props)->toHaveKey('validacionEnVivo');
    expect($props)->not->toHaveKey('catalogo');
    expect($props)->not->toHaveKey('validaciones');
    expect($props['validacionEnVivo']['totales']['cursos'])->toBe(1);
});

it('evalúa con la especialidad que abren los cursos de la pantalla', function () {
    // La ruta guardada abre con Calidad. En pantalla quedó un solo curso de
    // Cadena de Suministros, que no admite el papel de ancla: si el panel siguiera
    // preguntándole a la especialidad guardada, aprobaría una lista que la emisión
    // rechaza, y el asesor se enteraría con el lead esperando.
    $route = FunnelFactory::route(['spc'], 'calidad');

    $this->actingAs(FunnelFactory::author())
        ->post("/embudos/{$route->funnel_id}/validacion", [
            'items' => [['course_id' => courseId('gestion-de-inventarios')]],
            'copy_text' => [],
        ])
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('validacionEnVivo.bloqueantes.0', fn (AssertableInertia $finding) => $finding
                ->where('codigo', 'SUPPLY_CHAIN_ROLE')
                ->etc()));
});
