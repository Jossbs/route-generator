<?php

declare(strict_types=1);

use App\Enums\LeadArea;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LearningRoute;
use App\Services\RouteComposition\Support\RecommendedTopUp;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FunnelFactory;

/*
 * Lo que el asesor captura, y lo que el motor hace con ello.
 *
 * El teléfono dejó de ser obligatorio en el gancho —el asesor arma la ruta
 * mientras conversa y no siempre lo tiene todavía— y el área entró como el único
 * dato tipificado de la captura: es el que decide qué se recomienda además de lo
 * que el lead pidió, y el que se guarda de una sola manera escríbase como se
 * escriba.
 */

/** La ruta curada que el asesor elige. Una sola por prueba. */
function embudoDeCaptura(): int
{
    return Funnel::query()->value('id')
        ?? FunnelFactory::available(
            ['siete-herramientas-de-la-calidad', 'spc', 'amef'],
            'calidad',
        )->funnel_id;
}

function capturaDeLead(array $overrides = []): array
{
    return [
        'name' => 'Mariana Cruz',
        'occupation' => 'Ingeniera de procesos',
        'funnel_id' => embudoDeCaptura(),
        ...$overrides,
    ];
}

it('genera la ruta inicial sin teléfono', function () {
    Queue::fake();

    $this->actingAs(FunnelFactory::author())
        ->post('/rutas', capturaDeLead())
        ->assertRedirect();

    expect(Lead::sole()->phone)->toBeNull();
});

it('no cuelga dos leads sin teléfono de la misma ficha', function () {
    // Sin teléfono no hay con qué reencontrar, y juntar a dos personas distintas
    // en una ficha es peor que tener dos fichas.
    Queue::fake();
    $advisor = FunnelFactory::author();

    $this->actingAs($advisor)->post('/rutas', capturaDeLead());
    $this->actingAs($advisor)->post('/rutas', capturaDeLead(['name' => 'Otro lead']));

    expect(Lead::count())->toBe(2);
});

it('sigue exigiendo el teléfono en la ruta final, que es donde se reencuentra al lead', function () {
    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/final/meses', [
            ...capturaDeLead(),
            'full_name' => 'Mariana Cruz Aguilar',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
        ])
        ->assertSessionHasErrors('phone');
});

it('guarda el área que el asesor eligió de la lista', function () {
    Queue::fake();

    $this->actingAs(FunnelFactory::author())
        ->post('/rutas', capturaDeLead(['area' => 'mantenimiento']))
        ->assertRedirect();

    expect(Lead::sole()->area)->toBe(LeadArea::Mantenimiento);
    expect(Lead::sole()->area_other)->toBeNull();
});

it('estandariza el área escrita a mano', function () {
    Queue::fake();

    $this->actingAs(FunnelFactory::author())
        ->post('/rutas', capturaDeLead(['area' => 'otro', 'area_other' => 'CONTROL DE GESTION']))
        ->assertRedirect();

    expect(Lead::sole()->area)->toBeNull();
    expect(Lead::sole()->area_other)->toBe('Control de Gestion');
});

it('reconoce un área de la lista aunque venga escrita a mano', function () {
    Queue::fake();

    $this->actingAs(FunnelFactory::author())
        ->post('/rutas', capturaDeLead(['area' => 'otro', 'area_other' => 'QA']))
        ->assertRedirect();

    expect(Lead::sole()->area)->toBe(LeadArea::Calidad);
    expect(Lead::sole()->area_other)->toBeNull();
});

it('pide el área escrita cuando el asesor eligió «otra»', function () {
    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/propuesta', capturaDeLead(['area' => 'otro']))
        ->assertSessionHasErrors('area_other');
});

it('no exige el área: la captura sigue funcionando sin ella', function () {
    Queue::fake();

    $this->actingAs(FunnelFactory::author())
        ->post('/rutas', capturaDeLead())
        ->assertRedirect();

    expect(Lead::sole()->area)->toBeNull();
});

it('deja el select elegido cuando el área llega por URL', function () {
    $this->actingAs(FunnelFactory::author())
        ->get('/?area=Recursos+Humanos')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rutas/generar')
            ->where('precargado.area', 'rh')
            ->where('precargado.area_other', '')
            ->has('areas', count(LeadArea::cases())));
});

it('abre el campo abierto cuando el área de la URL no es de la lista', function () {
    $this->actingAs(FunnelFactory::author())
        ->get('/?area=comercio+exterior')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('precargado.area', 'otro')
            ->where('precargado.area_other', 'Comercio Exterior')
            ->etc());
});

it('recomienda por el área, no solo por la ocupación', function () {
    // Dos leads con la misma ocupación y distinta área no pueden recibir la misma
    // hoja: si la recibieran, el área sería un adorno de la captura.
    $advisor = FunnelFactory::author();

    $deRh = $this->actingAs($advisor)
        ->post('/rutas/propuesta', capturaDeLead(['area' => 'rh']))
        ->viewData('page')['props']['bloques'];

    $deMantenimiento = $this->actingAs($advisor)
        ->post('/rutas/propuesta', capturaDeLead(['area' => 'mantenimiento']))
        ->viewData('page')['props']['bloques'];

    $especialidades = fn (array $bloques): array => array_column($bloques, 'especialidad');

    expect($especialidades($deRh))->not->toBe($especialidades($deMantenimiento));
});

it('completa los cursos recomendados hasta el mínimo', function () {
    // El embudo nunca aporta recomendados: todo lo suyo es ruta principal. Los
    // dos o tres que acompañan los pone el motor, y son la parte que le muestra
    // al lead lo que no sabía que venía dentro.
    $embudo = FunnelFactory::available(['lean-manufacturing', 'smed'], 'lean');

    $respuesta = $this->actingAs(FunnelFactory::author())
        ->post('/rutas/propuesta', capturaDeLead([
            'area' => 'rh',
            'funnel_id' => $embudo->funnel_id,
        ]));

    $bloques = $respuesta->viewData('page')['props']['bloques'];

    $principales = array_filter($bloques, fn (array $bloque): bool => $bloque['principal']);
    $recomendados = array_sum(array_map(
        fn (array $bloque): int => count($bloque['cursos']),
        array_filter($bloques, fn (array $bloque): bool => ! $bloque['principal']),
    ));

    expect($principales)->toHaveCount(1);
    expect($recomendados)->toBe(RecommendedTopUp::OBJETIVO);
});

it('deja fijos todos los cursos del embudo, aunque sean de especialidades distintas', function () {
    // El caso que motivó la regla: una campaña que mezcla tres ramas. Las tres
    // son ruta principal —la campaña las eligió— y ninguna se puede podar,
    // aunque cada una conserve su propio título.
    $embudo = FunnelFactory::available(
        ['lean-manufacturing', 'smed', 'kaizen-mejora-continua', 'comunicacion-efectiva'],
        'lean',
    );

    $bloques = $this->actingAs(FunnelFactory::author())
        ->post('/rutas/propuesta', capturaDeLead(['funnel_id' => $embudo->funnel_id]))
        ->viewData('page')['props']['bloques'];

    $principales = array_values(array_filter($bloques, fn (array $b): bool => $b['principal']));
    $fijos = array_merge(...array_map(
        fn (array $b): array => array_column($b['cursos'], 'id'),
        $principales,
    ));

    // Tres especialidades, tres bloques, y los cuatro cursos marcados.
    expect($principales)->toHaveCount(3);
    expect($fijos)->toEqualCanonicalizing($embudo->orderedCourseIds());

    foreach ($principales as $bloque) {
        expect(array_column($bloque['cursos'], 'fijo'))->each->toBeTrue();
    }
});

it('recomienda de dos a tres cursos encima de la ruta principal', function () {
    $embudo = FunnelFactory::available(['lean-manufacturing', 'smed'], 'lean');

    $bloques = $this->actingAs(FunnelFactory::author())
        ->post('/rutas/propuesta', capturaDeLead([
            'area' => 'calidad',
            'funnel_id' => $embudo->funnel_id,
        ]))
        ->viewData('page')['props']['bloques'];

    $recomendados = array_merge(...array_map(
        fn (array $b): array => $b['cursos'],
        array_values(array_filter($bloques, fn (array $b): bool => ! $b['principal'])),
    ));

    expect(count($recomendados))
        ->toBeGreaterThanOrEqual(RecommendedTopUp::MINIMO)
        ->toBeLessThanOrEqual(RecommendedTopUp::OBJETIVO);

    expect(array_column($recomendados, 'fijo'))->each->toBeFalse();
});

it('deja la ruta principal primero y completa, con los cursos del embudo', function () {
    $embudo = FunnelFactory::available(['lean-manufacturing', 'smed', 'tpm'], 'lean');

    $bloques = $this->actingAs(FunnelFactory::author())
        ->post('/rutas/propuesta', capturaDeLead(['funnel_id' => $embudo->funnel_id]))
        ->viewData('page')['props']['bloques'];

    expect($bloques[0]['principal'])->toBeTrue();
    expect(array_column($bloques[0]['cursos'], 'id'))->toBe($embudo->orderedCourseIds());
});

it('no vuelve a sumar los recomendados que el asesor quitó', function () {
    // Los dos o tres recomendados son una propuesta, no un candado: si el asesor
    // los quita y emite su lista, volver a ponerlos sería discutirle en silencio
    // lo que acaba de decidir con el lead enfrente.
    Queue::fake();
    $advisor = FunnelFactory::author();
    $embudo = FunnelFactory::available(['lean-manufacturing', 'smed'], 'lean');
    $captura = capturaDeLead(['area' => 'rh', 'funnel_id' => $embudo->funnel_id]);

    $this->actingAs($advisor)->post('/rutas', [
        ...$captura,
        'course_ids' => $embudo->orderedCourseIds(),
    ]);

    expect(LearningRoute::sole()->items()->pluck('course_id')->all())
        ->toBe($embudo->orderedCourseIds());
});

it('devuelve la ruta principal completa aunque la petición llegue recortada', function () {
    // La pantalla no deja quitarla, así que una lista sin ella llegó por otro
    // camino: un formulario viejo, un reintento, un webhook. El documento no puede
    // prometer la rama por la que preguntó y entregarla a medias.
    Queue::fake();
    $embudo = FunnelFactory::available(['lean-manufacturing', 'smed', 'tpm'], 'lean');

    $this->actingAs(FunnelFactory::author())->post('/rutas', capturaDeLead([
        'funnel_id' => $embudo->funnel_id,
        'course_ids' => array_slice($embudo->orderedCourseIds(), 0, 1),
    ]));

    expect(LearningRoute::sole()->items()->pluck('course_id')->all())
        ->toBe($embudo->orderedCourseIds());
});
