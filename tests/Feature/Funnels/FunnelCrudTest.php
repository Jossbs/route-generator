<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\Funnel;
use App\Models\FunnelRouteCourse;
use App\Models\Specialization;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FunnelFactory;

/*
 * El CRUD de la ruta curada.
 *
 * Se captura un nombre y unos cursos, y nada más. Lo que no se captura importa
 * tanto como lo que sí: precios, ángulo comercial y vigencia tienen columna pero
 * están bloqueados o dormidos, y una columna dormida con formulario deja de estar
 * dormida.
 */

it('lista las rutas con su fecha y su número de cursos', function () {
    $primera = FunnelFactory::route(['siete-herramientas-de-la-calidad', 'spc'], 'calidad');
    $segunda = FunnelFactory::route(['dmaic'], 'sixsigma', [], ['slug' => 'six-sigma']);

    $this->actingAs(FunnelFactory::author())
        ->get('/embudos')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('embudos/index')
            ->has('embudos.data', 2)
            // La última armada primero: es casi siempre la que se busca.
            ->where('embudos.data.0.id', $segunda->funnel_id)
            ->where('embudos.data.0.cursos', 1)
            ->where('embudos.data.1.id', $primera->funnel_id)
            ->where('embudos.data.1.cursos', 2)
            ->has('embudos.data.0.creado_en'));
});

it('filtra por nombre y por especialidad', function () {
    FunnelFactory::route(['siete-herramientas-de-la-calidad'], 'calidad', [], [
        'slug' => 'calidad-octubre',
        'internal_name' => 'Calidad octubre',
    ]);

    FunnelFactory::route(['dmaic'], 'sixsigma', [], [
        'slug' => 'six-sigma-octubre',
        'internal_name' => 'Six Sigma octubre',
    ]);

    $author = FunnelFactory::author();
    $sixsigma = Specialization::where('slug', 'sixsigma')->value('id');

    $this->actingAs($author)
        ->get('/embudos?q=Calidad')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('embudos.data', 1)
            ->where('embudos.data.0.nombre', 'Calidad octubre'));

    $this->actingAs($author)
        ->get("/embudos?especialidad={$sixsigma}")
        ->assertInertia(fn (AssertableInertia $page) => $page->has('embudos.data', 1)
            ->where('embudos.data.0.nombre', 'Six Sigma octubre'));
});

it('crea la ruta con su nombre y sus cursos, y la deja disponible para el asesor', function () {
    $author = FunnelFactory::author();
    $cursos = Course::whereIn('slug', ['siete-herramientas-de-la-calidad', 'spc'])
        ->pluck('id')
        ->all();

    $this->actingAs($author)
        ->post('/embudos', [
            'name' => 'Calidad y control estadístico',
            'items' => [
                ['course_id' => $cursos[0]],
                ['course_id' => $cursos[1]],
            ],
        ])
        ->assertRedirect();

    $funnel = Funnel::sole();

    expect($funnel->internal_name)->toBe('Calidad y control estadístico');
    expect($funnel->created_by)->toBe($author->id);
    // Disponible desde que existe: no hay paso de publicación que alguien deba
    // recordar dar.
    expect($funnel->isSelectable())->toBeTrue();
    expect($funnel->primaryRoute->orderedCourseIds())->toHaveCount(2);
});

it('deriva el identificador del nombre y no pide convención alguna', function () {
    $this->actingAs(FunnelFactory::author())
        ->post('/embudos', ['name' => 'Calidad y Mejora Continua', 'items' => []])
        ->assertRedirect();

    expect(Funnel::sole()->slug)->toBe('calidad-y-mejora-continua');
});

it('deriva la especialidad que abre la ruta de los cursos elegidos', function () {
    $cursos = Course::whereIn('slug', ['dmaic', 'white-belt-six-sigma', 'poka-yoke'])
        ->pluck('id')
        ->all();

    $this->actingAs(FunnelFactory::author())
        ->post('/embudos', [
            'name' => 'Six Sigma con apoyo de calidad',
            'items' => array_map(fn (int $id): array => ['course_id' => $id], $cursos),
        ])
        ->assertRedirect();

    expect(Funnel::sole()->primaryRoute->main_specialization_id)
        ->toBe(Specialization::where('slug', 'sixsigma')->value('id'));
});

it('no deja que Cadena de Suministros abra la ruta aunque sea mayoría', function () {
    // Tiene un solo curso y no se presenta sola: si abriera el documento, el lead
    // leería un bloque de un curso al lado de otros de ocho.
    $cursos = Course::whereIn('slug', ['gestion-de-inventarios', 'poka-yoke'])
        ->pluck('id')
        ->all();

    $this->actingAs(FunnelFactory::author())
        ->post('/embudos', [
            'name' => 'Inventarios y calidad',
            'items' => array_map(fn (int $id): array => ['course_id' => $id], $cursos),
        ])
        ->assertRedirect();

    expect(Funnel::sole()->primaryRoute->main_specialization_id)
        ->toBe(Specialization::where('slug', 'calidad')->value('id'));
});

it('no acepta precios ni referencias de campaña, que están bloqueados o dormidos', function () {
    $this->actingAs(FunnelFactory::author())
        ->post('/embudos', [
            'name' => 'Calidad octubre',
            'items' => [],
            'list_price_cents' => 419900,
            'promo_price_cents' => 209900,
            'discount_label' => '50% de descuento',
            'utm_campaign' => 'oct-calidad',
            'angle' => 'descuento',
        ])
        ->assertRedirect();

    $funnel = Funnel::sole();

    expect($funnel->list_price_cents)->toBeNull();
    expect($funnel->promo_price_cents)->toBeNull();
    expect($funnel->discount_label)->toBeNull();
    expect($funnel->utm_campaign)->toBeNull();
    expect($funnel->angle)->toBeNull();
});

it('reescribe la lista al guardar, sin dejar cursos sueltos', function () {
    $route = FunnelFactory::route(['siete-herramientas-de-la-calidad', 'spc'], 'calidad');
    $spc = Course::where('slug', 'spc')->value('id');

    $this->actingAs(FunnelFactory::author())
        ->put("/embudos/{$route->funnel_id}", [
            'name' => 'Solo control estadístico',
            'items' => [['course_id' => $spc]],
        ])
        ->assertRedirect();

    expect($route->funnel->refresh()->internal_name)->toBe('Solo control estadístico');
    expect($route->refresh()->orderedCourseIds())->toBe([$spc]);
    expect(FunnelRouteCourse::count())->toBe(1);
});

it('conserva el identificador al renombrar, porque el snapshot se refiere a él', function () {
    $route = FunnelFactory::route(['spc'], 'calidad', [], ['slug' => 'calidad-octubre']);

    $this->actingAs(FunnelFactory::author())
        ->put("/embudos/{$route->funnel_id}", [
            'name' => 'Otro nombre completamente distinto',
            'items' => [['course_id' => $route->orderedCourseIds()[0]]],
        ])
        ->assertRedirect();

    expect($route->funnel->refresh()->slug)->toBe('calidad-octubre');
});

it('borra la ruta sin tocar los documentos que ya salieron', function () {
    $route = FunnelFactory::route(['spc'], 'calidad');

    $this->actingAs(FunnelFactory::author())
        ->delete("/embudos/{$route->funnel_id}")
        ->assertRedirect();

    expect(Funnel::whereKey($route->funnel_id)->exists())->toBeFalse();
    expect(Funnel::withTrashed()->whereKey($route->funnel_id)->exists())->toBeTrue();
});

it('el armador entrega catálogo, cursos y validador en la misma pantalla', function () {
    $route = FunnelFactory::route(['siete-herramientas-de-la-calidad', 'spc'], 'calidad');

    $this->actingAs(FunnelFactory::author())
        ->get("/embudos/{$route->funnel_id}/editar")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('embudos/editar')
            ->where('embudo.nombre', 'EJEMPLO · ruta de prueba')
            ->has('embudo.cursos', 2)
            ->has('validacion.bloqueantes')
            ->has('validacion.totales')
            ->has('catalogo')
            ->has('especialidades')
            ->has('traslapes')
            ->where('permisos.editar', true));
});

it('abre el alta con el catálogo y sin un solo campo de campaña', function () {
    $this->actingAs(FunnelFactory::author())
        ->get('/embudos/nuevo')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('embudos/nuevo')
            ->has('catalogo')
            ->has('especialidades')
            ->has('traslapes')
            ->missing('angulos'));
});

it('avisa qué cursos ya viven en otra ruta, sin impedir nada', function () {
    $existente = FunnelFactory::route(['siete-herramientas-de-la-calidad', 'spc'], 'calidad', [], [
        'slug' => 'calidad-octubre',
        'internal_name' => 'Calidad octubre',
    ]);

    $compartido = $existente->orderedCourseIds()[0];

    $this->actingAs(FunnelFactory::author())
        ->get('/embudos/nuevo')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where("traslapes.{$compartido}.0.nombre", 'Calidad octubre')
            ->etc());

    // Y el mismo curso se puede volver a elegir: un curso engancha desde dos
    // ramas a propósito.
    $this->actingAs(FunnelFactory::author())
        ->post('/embudos', [
            'name' => 'Otra ruta con el mismo curso',
            'items' => [['course_id' => $compartido]],
        ])
        ->assertSessionHasNoErrors();
});

it('no se muestra a sí misma como traslape al editarse', function () {
    $route = FunnelFactory::route(['spc'], 'calidad');

    $this->actingAs(FunnelFactory::author())
        ->get("/embudos/{$route->funnel_id}/editar")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('traslapes', [])
            ->etc());
});
