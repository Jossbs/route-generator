<?php

declare(strict_types=1);

use App\Enums\RouteStatus;
use App\Models\CopyBlock;
use App\Models\Course;
use App\Models\LearningRoute;
use App\Services\Documents\AudioScript;
use App\Services\Documents\InitialRouteScriptCopy;
use App\Services\Documents\RouteDuration;
use App\Services\Documents\WrittenMessage;
use App\Services\RouteComposition\Support\RecommendedTopUp;
use App\Services\RouteGeneration\PayloadHash;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FunnelFactory;

/*
 * El guión del audio que acompaña a la ruta inicial (2026-09-22).
 *
 * No tiene captura propia: se arma con la misma ruta, y usa el nombre, la duración y
 * el total de constancias del PDF. Se
 * congela en el snapshot, y la bandeja es su historial.
 */

function embudoDelGuion(): int
{
    return FunnelFactory::available(
        ['lean-manufacturing', 'kaizen-mejora-continua'],
        'lean',
    )->funnel_id;
}

function emitirConGuion(array $overrides = []): LearningRoute
{
    Queue::fake();

    test()->actingAs(FunnelFactory::author())->post('/rutas', [
        'name' => 'Mariana',
        'phone' => '4421185502',
        'occupation' => 'Ingeniera de procesos',
        'area' => 'calidad',
        'context' => null,
        ...$overrides,
        'funnel_id' => $overrides['funnel_id'] ?? embudoDelGuion(),
    ]);

    return LearningRoute::query()->latest('id')->firstOrFail();
}

it('personaliza el texto autorizado con las mismas cifras que el PDF', function () {
    $route = emitirConGuion(['context' => 'Quiere mejorar sus procesos']);
    $payload = $route->snapshot;
    $guion = $payload['guion'];
    $count = $payload['ruta']['totales']['cursos'];

    expect($route->status)->toBe(RouteStatus::Validated)
        ->and($route->snapshotIsIntact())->toBeTrue()
        ->and($guion['texto'])
        ->toStartWith('¡Hola, Mariana! ¿Cómo estás? Te saluda Eje Cursos, asesora de Desarrollo Profesional SyS.')
        ->toContain('La preparé tomando en cuenta tu perfil y seleccionando los cursos que considero más adecuados para tu desarrollo profesional.')
        ->toContain('Con esta ruta, en aproximadamente '.RouteDuration::for($payload, spoken: true))
        ->toContain(RouteDuration::number($count).' constancias DC-3 ante la STPS')
        ->toContain('sesenta y cuatro cursos con sus certificaciones durante 12 meses, sin pagar cada uno por separado.')
        ->toContain('También contamos con diferentes facilidades de pago.')
        ->toContain('Me gustaría conocer tu opinión, Mariana. ¿Qué te pareció la ruta? ¿Te comparto las opciones de pago o prefieres que te explique más sobre las certificaciones?')
        ->not->toMatch('/\{[a-z_]+\}/')
        ->and($guion['menciones_nombre'])->toBe(2)
        ->and(array_column($guion['bloques'], 'clave'))
        ->toBe(['saludo', 'conexion', 'ruta', 'inicio', 'pago', 'cierre']);
});

it('mantiene el audio de referencia entre 40 y 60 segundos', function (array $overrides) {
    $guion = emitirConGuion($overrides)->snapshot['guion'];

    expect($guion['segundos'])
        ->toBeGreaterThanOrEqual(AudioScript::SEGUNDOS_MIN)
        ->toBeLessThanOrEqual(AudioScript::SEGUNDOS_MAX);
})->with([
    'sin contexto' => [[]],
    'con contexto' => [['context' => 'Quiere liderar el área de mejora continua.']],
    'estudiante' => [['area' => 'estudiante', 'occupation' => 'Estudiante de ingeniería']],
]);

it('reproduce las cifras del ejemplo y las cambia para otra ruta', function () {
    $route = emitirConGuion(['name' => 'Inge', 'context' => 'Quiere mejorar su perfil']);
    $payload = $route->snapshot;
    $payload['asesor']['nombre'] = 'Joseline';
    $payload['ruta']['totales'] = ['cursos' => 8, 'programas' => 8, 'horas_netas' => 126];

    $audio = app(AudioScript::class)->for($payload, $route->lead);
    $message = app(WrittenMessage::class)->for($payload, $route->lead);

    expect($audio['texto'])->toContain('Con esta ruta, en aproximadamente siete semanas podrías obtener ocho constancias DC-3 ante la STPS.')
        ->and($message['texto'])->toStartWith('¡Inge, tu *ruta de capacitación personalizada* ya está lista! 🎯')
        ->toContain('🎓 En *7 semanas podrías obtener 8 constancias DC-3 ante la STPS*.');

    $payload['lead']['nombre'] = 'Ana Lucía';
    $payload['asesor']['nombre'] = 'Daniela';
    $payload['ruta']['totales'] = ['cursos' => 1, 'programas' => 1, 'horas_netas' => 15];
    $payload['catalogo']['cursos'] = 63;
    $audio = app(AudioScript::class)->for($payload, $route->lead);
    $message = app(WrittenMessage::class)->for($payload, $route->lead);

    expect($audio['texto'])->toStartWith('¡Hola, Ana Lucía! ¿Cómo estás? Te saluda Daniela, asesora de Desarrollo Profesional SyS.')
        ->toContain('una semana podrías obtener una constancia DC-3')
        ->toContain('sesenta y tres cursos con sus certificaciones durante 12 meses')
        ->not->toContain('Joseline', 'ocho constancias', 'sesenta y cuatro')
        ->and($message['texto'])->toContain('1 constancia DC-3', '63 cursos + certificaciones durante 12 meses')
        ->not->toContain('8 constancias', '64 cursos');
});

it('resalta los datos personales y las cifras para quien graba', function () {
    $blocks = emitirConGuion()->snapshot['guion']['bloques'];
    $segments = collect($blocks)->flatMap(fn (array $block): array => $block['segmentos']);

    expect($segments->firstWhere('dato', 'nombre'))
        ->toMatchArray(['texto' => 'Mariana', 'enfasis' => true]);
    expect($segments->firstWhere('dato', 'duracion_audio')['enfasis'])->toBeTrue();
    expect($segments->firstWhere('dato', 'constancias_audio')['enfasis'])->toBeTrue();
});

it('no atribuye una conversación cuando no hay contexto capturado', function () {
    $route = emitirConGuion();

    expect($route->snapshot['guion']['texto'])
        ->toContain('La preparé tomando en cuenta tu perfil')
        ->not->toContain('lo que me contaste')
        ->and($route->snapshot['mensaje']['texto'])
        ->toContain('La preparé considerando tu perfil y las certificaciones que pueden ayudarte a alcanzar tus objetivos profesionales.');
});

it('adapta el audio a estudiantes sin atribuirles un trabajo', function () {
    $texto = emitirConGuion(['area' => 'estudiante', 'occupation' => 'Estudiante de ingeniería'])
        ->snapshot['guion']['texto'];

    expect($texto)->toContain('para tu desarrollo profesional')
        ->not->toContain('en tu trabajo');
});

it('recalcula las constancias y la duración si se retiran recomendados', function () {
    $curados = FunnelFactory::available(['lean-manufacturing', 'kaizen-mejora-continua'], 'lean');
    $route = emitirConGuion([
        'funnel_id' => $curados->funnel_id,
        'course_ids' => $curados->orderedCourseIds(),
    ]);

    expect($route->snapshot['guion']['texto'])->toContain('dos constancias DC-3')
        ->and($route->snapshot['mensaje']['texto'])->toContain('2 constancias DC-3')
        ->not->toContain('3 meses y medio', '10 constancias DC-3');
});

it('arma el mensaje autorizado en seis párrafos listos para WhatsApp', function () {
    $message = emitirConGuion(['context' => 'Quiere liderar el área de mejora continua.'])->snapshot['mensaje'];

    expect($message['texto'])
        ->toStartWith('¡Mariana, tu *ruta de capacitación personalizada* ya está lista! 🎯')
        ->toContain('📚 Además, tu membresía incluye *64 cursos + certificaciones durante 12 meses*, sin pagar cada curso por separado.')
        ->toContain('💳 También contamos con facilidades de pago para que puedas comenzar tu capacitación.')
        ->toEndWith('👉 *¿Qué te pareció la ruta que preparé para ti?* 😊 ¿Te gustaría conocer las facilidades de pago o más detalles sobre las certificaciones?')
        ->and(explode("\n\n", $message['texto']))->toHaveCount(6)
        ->and(array_column($message['bloques'], 'clave'))->toBe(['saludo', 'conexion', 'ruta', 'inicio', 'pago', 'cierre']);
});

it('muestra el complemento nuevo en rutas anteriores sin alterar su snapshot', function () {
    $route = emitirConGuion(['context' => 'Quiere mejorar sus procesos']);
    $snapshot = $route->snapshot;
    $snapshot['guion'] = ['texto' => 'Audio anterior'];
    $snapshot['mensaje'] = ['texto' => 'Mensaje anterior'];
    $hash = PayloadHash::of($snapshot);
    $route->update(['snapshot' => $snapshot, 'snapshot_hash' => $hash]);
    $expected = app(WrittenMessage::class)->for($snapshot, $route->lead);

    $this->get("/rutas/{$route->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('guion.version', InitialRouteScriptCopy::VERSION)
            ->where('mensaje.texto', $expected['texto']));
    $this->get('/rutas')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rutas.data.0.mensaje', $expected['texto']));

    expect($route->fresh()->snapshot)->toEqual($snapshot)
        ->and($route->fresh()->snapshot_hash)->toBe($hash)
        ->and($route->fresh()->snapshotIsIntact())->toBeTrue();
});

it('escanea el mensaje igual que el documento', function () {
    // El mensaje es copy de cara al lead: si trae una frase prohibida, la ruta
    // no sale, igual que con el PDF.
    CopyBlock::query()->where('slug', 'mensaje.cierre')
        ->update(['body' => 'Es equivalente a un Green Belt 👇']);

    Queue::fake();
    $this->actingAs(FunnelFactory::author())->postJson('/rutas', [
        'name' => 'Mariana',
        'occupation' => 'Ingeniera de procesos',
        'area' => 'calidad',
        'funnel_id' => embudoDelGuion(),
    ])->assertUnprocessable()->assertJsonValidationErrors('course_ids');

    expect(LearningRoute::count())->toBe(0);
});

it('personaliza el audio con más de tres adicionales elegidos por el asesor', function () {
    Queue::fake();
    $curados = FunnelFactory::available(['lean-manufacturing'], 'lean');
    $extra = Course::query()
        ->whereNotIn('id', $curados->orderedCourseIds())
        ->limit(RecommendedTopUp::OBJETIVO + 1)
        ->pluck('id')
        ->all();

    $this->actingAs(FunnelFactory::author())
        ->post('/rutas', [
            'name' => 'Mariana',
            'occupation' => 'Ingeniera de procesos',
            'area' => 'calidad',
            'funnel_id' => $curados->funnel_id,
            'course_ids' => [...$curados->orderedCourseIds(), ...$extra],
        ])
        ->assertSessionHasNoErrors()->assertRedirect();

    $route = LearningRoute::sole();
    expect($route->itineraryCourseIds())->toBe([...$curados->orderedCourseIds(), ...$extra])
        ->and($route->snapshot['guion']['texto'])->toContain(RouteDuration::number($route->course_count).' constancias DC-3 ante la STPS');
});

it('muestra el guión en la ficha y deja copiar el mensaje desde la bandeja', function () {
    $route = emitirConGuion();

    $this->get("/rutas/{$route->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rutas/detalle')
            ->where('guion.texto', $route->snapshot['guion']['texto'])
            ->where('mensaje.texto', $route->snapshot['mensaje']['texto']));

    $this->get('/rutas')
        ->assertInertia(fn (AssertableInertia $page) => $page
            // El guión se lee en la ficha: no se copia ni se pega en ningún lado.
            ->missing('rutas.data.0.guion')
            ->where('rutas.data.0.mensaje', $route->snapshot['mensaje']['texto']));
});
