<?php

declare(strict_types=1);

use App\Enums\FunnelCourseKind;
use App\Enums\FunnelState;
use App\Models\FunnelRoute;
use Tests\Support\FunnelFactory;

/*
 * La composición de una ruta curada.
 *
 * El orden que llega es el orden que queda: la posición se deriva del arreglo y no
 * se captura, porque reordenar tiene que ser mover un renglón y no renumerar a
 * mano.
 */

it('agrega una ruta al final del embudo', function () {
    $existing = FunnelFactory::route(['spc'], 'calidad');

    $this->actingAs(FunnelFactory::author())
        ->post("/embudos/{$existing->funnel_id}/rutas", ['name' => 'Calidad para supervisión'])
        ->assertRedirect();

    $created = FunnelRoute::where('funnel_id', $existing->funnel_id)->orderByDesc('position')->first();

    expect($created->name)->toBe('Calidad para supervisión');
    expect($created->position)->toBe(2);
    expect($created->duration_months)->toBe(12);
});

it('guarda la composición en el orden recibido', function () {
    $route = FunnelFactory::route(['spc'], 'calidad');

    $this->actingAs(FunnelFactory::author())
        ->put("/embudos/{$route->funnel_id}/rutas/{$route->id}", [
            'name' => 'Calidad de piso',
            'archetype_id' => null,
            'main_specialization_id' => $route->main_specialization_id,
            'duration_months' => 12,
            'intro_text' => 'Una secuencia sugerida, no un calendario.',
            'closing_text' => null,
            'items' => [
                ['course_id' => courseId('siete-herramientas-de-la-calidad'), 'suggested_month' => 1],
                ['course_id' => courseId('poka-yoke'), 'suggested_month' => 2],
                ['course_id' => courseId('spc'), 'suggested_month' => 4],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $route->refresh()->load('items');

    expect($route->orderedCourseIds())->toBe([
        courseId('siete-herramientas-de-la-calidad'),
        courseId('poka-yoke'),
        courseId('spc'),
    ]);
    expect($route->items->pluck('position')->all())->toBe([1, 2, 3]);
    expect($route->monthsByCourseId()[courseId('spc')])->toBe(4);
});

it('quita los cursos que ya no vienen en la lista', function () {
    $route = FunnelFactory::route(['siete-herramientas-de-la-calidad', 'poka-yoke', 'spc'], 'calidad');

    $this->actingAs(FunnelFactory::author())
        ->put("/embudos/{$route->funnel_id}/rutas/{$route->id}", [
            'name' => $route->name,
            'main_specialization_id' => $route->main_specialization_id,
            'duration_months' => 12,
            'items' => [['course_id' => courseId('spc'), 'suggested_month' => 1]],
        ])
        ->assertSessionHasNoErrors();

    expect($route->refresh()->orderedCourseIds())->toBe([courseId('spc')]);
});

it('no toca las columnas dormidas aunque lleguen en la petición', function () {
    $route = FunnelFactory::route(['spc'], 'calidad');

    $this->actingAs(FunnelFactory::author())
        ->put("/embudos/{$route->funnel_id}/rutas/{$route->id}", [
            'name' => $route->name,
            'duration_months' => 12,
            'choose_n' => 3,
            'items' => [[
                'course_id' => courseId('spc'),
                'suggested_month' => 1,
                'kind' => 'elegible',
                'outcome_phrase_override' => 'Domina el control estadístico en 30 días',
            ]],
        ])
        ->assertSessionHasNoErrors();

    $route->refresh()->load('items');

    expect($route->choose_n)->toBeNull();
    expect($route->items->first()->kind)->toBe(FunnelCourseKind::Fijo);
    expect($route->items->first()->outcome_phrase_override)->toBeNull();
});

it('no deja editar una ruta desde el embudo equivocado', function () {
    $mine = FunnelFactory::route(['spc'], 'calidad');
    $other = FunnelFactory::route(['amef'], 'calidad', funnelAttributes: ['slug' => '2026-11-otro-urgencia']);

    $this->actingAs(FunnelFactory::author())
        ->put("/embudos/{$mine->funnel_id}/rutas/{$other->id}", [
            'name' => 'Secuestrada',
            'duration_months' => 12,
            'items' => [],
        ])
        ->assertNotFound();

    expect($other->refresh()->name)->toBe('Ruta de prueba');
});

it('no deja editar las rutas de un embudo archivado', function () {
    $route = FunnelFactory::route(['spc'], 'calidad', funnelAttributes: [
        'state' => FunnelState::Archivado,
    ]);

    $this->actingAs(FunnelFactory::author())
        ->delete("/embudos/{$route->funnel_id}/rutas/{$route->id}")
        ->assertForbidden();

    expect(FunnelRoute::whereKey($route->id)->exists())->toBeTrue();
});
