<?php

declare(strict_types=1);

use App\Enums\RouteStatus;
use App\Models\CopyBlock;
use App\Models\Course;
use App\Models\LearningRoute;
use App\Services\Documents\AudioScript;
use App\Services\RouteComposition\Support\RecommendedTopUp;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FunnelFactory;

/*
 * El guión del audio que acompaña a la ruta inicial (2026-09-22).
 *
 * No tiene captura propia: se arma con la misma ruta, así que nombra el primer y
 * el último curso del embudo y los mismos recomendados que lleva el PDF. Se
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

it('arma el guión con la misma ruta y lo congela en el snapshot', function () {
    $route = emitirConGuion();
    $guion = $route->snapshot['guion'];

    $principal = collect($route->snapshot['ruta']['cursos'])
        ->where('rol', '!=', 'complementaria')->pluck('nombre');
    $regalos = collect($route->snapshot['ruta']['cursos'])
        ->where('rol', 'complementaria')->pluck('nombre');

    expect($guion['texto'])
        ->toContain('Mariana')
        ->toContain('tu trabajo en Calidad')
        ->toContain('arranca con '.$principal->first())
        ->toContain('esto es apenas el inicio')
        ->toContain('12 meses')
        ->toContain('facilidades de pago');

    // Los recomendados se cuentan, no se nombran: el PDF los nombra y así el
    // lead tiene un motivo para abrirlo (2026-09-23).
    expect($regalos)->toHaveCount(2);

    foreach ($regalos as $regalo) {
        expect($guion['texto'])->not->toContain($regalo);
    }

    expect($guion['texto'])->toContain('dos cursos más')
        ->and($guion['menciones_nombre'])->toBe(2)
        ->and(array_column($guion['bloques'], 'clave'))
        ->toBe(['saludo', 'conexion', 'ruta', 'inicio', 'cierre']);
});

it('dura entre 45 y 60 segundos en cada variante', function (array $overrides) {
    $guion = emitirConGuion($overrides)->snapshot['guion'];

    expect($guion['segundos'])
        ->toBeGreaterThanOrEqual(AudioScript::SEGUNDOS_MIN)
        ->toBeLessThanOrEqual(AudioScript::SEGUNDOS_MAX);
})->with([
    'por área' => [[]],
    'con lo que contó' => [['context' => 'quiere liderar el área de mejora continua.']],
    'estudiante' => [['area' => 'estudiante', 'occupation' => 'Estudiante de ingeniería']],
]);

it('no vuelve a decir «certificaciones» como conteo del catálogo', function () {
    $texto = emitirConGuion()->snapshot['guion']['texto'];

    expect($texto)
        ->not->toContain('64 certificaciones')
        ->not->toContain('certificado verificable')
        ->toContain('constancia DC-3');
});

it('marca qué parte es dato y dónde sube la voz', function () {
    $segmentos = emitirConGuion()->snapshot['guion']['bloques'][0]['segmentos'];

    expect($segmentos[1])->toMatchArray(['texto' => 'Mariana', 'dato' => 'nombre', 'enfasis' => true]);
});

it('devuelve lo que el lead contó cuando lo hay', function () {
    $texto = emitirConGuion(['context' => 'quiere liderar el área de mejora continua.'])
        ->snapshot['guion']['texto'];

    expect($texto)->toContain('me contaste: quiere liderar el área de mejora continua. Y créeme que te entiendo');
});

it('baja la mayúscula de lo que contó el lead al meterlo después de dos puntos', function () {
    $texto = emitirConGuion(['context' => 'QUIERE LIDERAR EL ÁREA DE MEJORA CONTINUA'])
        ->snapshot['guion']['texto'];

    expect($texto)->toContain('me contaste: quiere liderar el área de mejora continua.');
});

it('nombra la ocupación cuando el lead no trabaja todavía', function () {
    $texto = emitirConGuion(['area' => 'estudiante', 'occupation' => 'Estudiante de ingeniería'])
        ->snapshot['guion']['texto'];

    expect($texto)
        ->not->toContain('tu trabajo en')
        ->toContain('me compartiste como estudiante de ingeniería');
});

it('ajusta el guión a cuántos recomendados dejó el asesor', function () {
    // Quitó los dos: el audio ya no ofrece «cursos más» que el PDF no trae.
    $curados = FunnelFactory::available(['lean-manufacturing', 'kaizen-mejora-continua'], 'lean');

    $route = emitirConGuion([
        'funnel_id' => $curados->funnel_id,
        'course_ids' => $curados->orderedCourseIds(),
    ]);

    expect($route->snapshot['guion']['texto'])->not->toContain('más que te va')
        ->and($route->snapshot['mensaje']['texto'])->not->toContain('extra');
});

it('arma el mensaje que sustituye al audio, sin calcar el guión', function () {
    $route = emitirConGuion(['context' => 'quiere liderar el área de mejora continua.']);
    $mensaje = $route->snapshot['mensaje'];
    $principal = collect($route->snapshot['ruta']['cursos'])
        ->where('rol', '!=', 'complementaria')->pluck('nombre');

    expect($mensaje['texto'])
        ->toStartWith('¡Hola, Mariana! 👋')
        ->toContain('me contaste: quiere liderar el área de mejora continua.')
        // Las negritas ya van con la sintaxis de WhatsApp, pegadas al texto.
        ->toContain('Arrancas con *'.$principal->first().'*')
        ->toContain('*apenas el comienzo*')
        ->toContain('12 meses')
        ->toContain('facilidades de pago')
        ->not->toMatch('/(^|\s)\*\s|\s\*($|[\s.,])/u')
        ->and(array_column($mensaje['bloques'], 'clave'))
        ->toBe(['saludo', 'conexion', 'ruta', 'inicio', 'cierre']);

    // Mismo trabajo que el audio, otras frases. Lo único que se repite es lo que
    // contó el lead, que es suyo.
    $frasesDelGuion = preg_split('/(?<=[.:?!])\s+/u', $route->snapshot['guion']['texto']);

    foreach ($frasesDelGuion as $frase) {
        if (mb_strlen($frase) > 30 && ! str_contains($frase, 'mejora continua')) {
            expect($mensaje['texto'])->not->toContain($frase);
        }
    }
});

it('escanea el mensaje igual que el documento', function () {
    // El mensaje es copy de cara al lead: si trae una frase prohibida, la ruta
    // no sale, igual que con el PDF.
    CopyBlock::query()->where('slug', 'mensaje.cierre')
        ->update(['body' => 'Es equivalente a un Green Belt 👇']);

    $route = emitirConGuion();

    expect($route->status)->toBe(RouteStatus::Rejected);
});

it('no deja pasar más recomendados de los que menciona el audio', function () {
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
            'funnel_id' => $curados->funnel_id,
            'course_ids' => [...$curados->orderedCourseIds(), ...$extra],
        ])
        ->assertSessionHasErrors('course_ids');

    expect(LearningRoute::count())->toBe(0);
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
