<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\DocumentType;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LearningRoute;
use App\Services\RouteGeneration\Data\RouteInput;
use App\Services\RouteGeneration\RouteGenerator;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FunnelFactory;

/*
 * El rediseño de los dos documentos, decidido el 2026-09-24.
 *
 * La ruta inicial pasa a cuatro hojas —portada con el nombre del embudo, la ruta,
 * «Quiénes somos» y el siguiente paso— y la final se lee como expediente: ficha
 * de membresía, cronograma, inventario marcado y primeros pasos. El asesor puede
 * ajustar tres párrafos a mano, y lo ajustado pasa por el mismo escaneo que el
 * resto del documento.
 */

function redisenoEmbudo(array $cursos = ['siete-herramientas-de-la-calidad', 'spc', 'amef'], string $ancla = 'calidad'): int
{
    return FunnelFactory::available($cursos, $ancla)->funnel_id;
}

function redisenoCaptura(array $overrides = []): array
{
    return [
        'name' => 'Mariana Cruz',
        'phone' => '4421185502',
        'occupation' => 'Ingeniera de procesos',
        'funnel_id' => $overrides['funnel_id'] ?? Funnel::query()->value('id') ?? redisenoEmbudo(),
        'context' => 'quiere dejar de apagar fuegos en la línea',
        ...$overrides,
    ];
}

function redisenoCapturaFinal(array $overrides = []): array
{
    return [
        'full_name' => 'Mariana Cruz Aguilar',
        'phone' => '4421185502',
        'occupation' => 'Coordinadora de calidad',
        'funnel_id' => $overrides['funnel_id'] ?? redisenoEmbudo(['dmaic', 'siete-herramientas-de-la-calidad', 'spc'], 'sixsigma'),
        'context' => null,
        'plan' => 'expert_anual',
        'price' => '4199.00',
        'starts_on' => '2026-10-01',
        'ends_on' => '2027-09-30',
        'weekly_hours' => 15,
        ...$overrides,
    ];
}

/** Emite la ruta inicial como la emite el asesor y devuelve su documento. */
function redisenoGancho(array $captura): array
{
    Queue::fake();

    test()->actingAs(FunnelFactory::author())
        ->post('/rutas', $captura)
        ->assertSessionHasNoErrors();

    $ruta = LearningRoute::query()->latest('id')->firstOrFail();

    $html = test()->actingAs(FunnelFactory::author())
        ->get('/rutas/'.$ruta->id.'/documento')
        ->assertOk()
        ->getContent();

    return [$ruta, $html];
}

// ---------------------------------------------------------------- ruta inicial

it('titula la portada con el nombre del embudo y nombra el curso por el que arranca', function () {
    [$ruta, $html] = redisenoGancho(redisenoCaptura());

    $primero = Course::where('slug', 'siete-herramientas-de-la-calidad')->sole()->name;

    expect($html)
        ->toContain('<h1 class="pt-h1">'.e('EJEMPLO · ruta de prueba'))
        ->toContain(e('Nos dijiste: «Quiere dejar de apagar fuegos en la línea». Por eso tu ruta arranca con '.$primero.'.'))
        // El primer curso lleva la marca: es por donde entra el lead.
        ->toContain('Empieza aquí');

    // «Ancla» es lenguaje del catálogo: el lead nunca lo lee.
    expect($html)->not->toContain('Especialización ancla');
});

it('dice las cifras de la ruta una sola vez, en la portada', function () {
    [, $html] = redisenoGancho(redisenoCaptura());

    preg_match_all('#<div class="pie">(.*?)</div>#s', $html, $pies);

    expect($pies[1])->not->toBeEmpty();

    foreach ($pies[1] as $pie) {
        expect($pie)->not->toContain('programas')->not->toContain('horas');
    }

    expect(substr_count($html, 'De contenido'))->toBe(1);
});

it('lleva una hoja de quiénes somos con lo que publica el sitio', function () {
    [, $html] = redisenoGancho(redisenoCaptura());

    expect($html)
        ->toContain('<h2>Aprendes con SyS</h2>')
        ->toContain('+80,000')
        ->toContain('Empresas que confían en SyS')
        ->toContain('img/dpsys/empresas/pfizer.png')
        ->toContain('img/dpsys/fotos/caterpillar.jpg')
        ->toContain('Reseña de Google');

    // Sin semblanzas de instructores: el sitio no las publica y el dueño las
    // dejó fuera (2026-09-24).
    expect($html)->not->toContain('Quién te enseña');
});

it('prefiere la reseña que habla de una especialidad de la ruta', function () {
    [, $html] = redisenoGancho(redisenoCaptura([
        'funnel_id' => redisenoEmbudo(['excel-basico', 'excel-intermedio'], 'excel'),
    ]));

    preg_match('#<div class="resenas">(.*?)</div>#s', $html, $resenas);

    preg_match_all('#<figcaption>(.*?)</figcaption>#s', $resenas[1] ?? '', $autores);

    // La que habla de Excel va primero.
    expect($autores[1])->toHaveCount(2);
    expect($autores[1][0])->toContain('Gemapao');
});

it('sin reseña de la especialidad, lleva las dos generales', function () {
    [, $html] = redisenoGancho(redisenoCaptura());

    expect($html)
        ->toContain('Fernanda Sánchez')
        ->toContain('Sebastián Franco')
        ->not->toContain('Gemapao');
});

it('cierra con la promesa de la ruta de doce meses, igual que el audio', function () {
    [$ruta, $html] = redisenoGancho(redisenoCaptura());

    expect($html)->toContain('te preparamos tu ruta de 12 meses');
    expect(json_encode($ruta->snapshot['guion'], JSON_UNESCAPED_UNICODE))->toContain('12 meses');
});

// ------------------------------------------------------------------ ruta final

it('rotula en la portada el alcance de la ruta anual', function () {
    Queue::fake();

    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/final', redisenoCapturaFinal())
        ->assertSessionHasNoErrors();

    $ruta = LearningRoute::query()->latest('id')->firstOrFail();
    $html = $this->actingAs(FunnelFactory::author())->get('/rutas/'.$ruta->id.'/documento')->getContent();

    $cursos = collect($ruta->snapshot['ruta']['cursos'])->where('capa', 'itinerario')->pluck('slug')->unique()->count();
    preg_match('#<section class="hoja[^\"]*">(.*?)</section>#s', $html, $portada);

    // La portada distingue la ruta curada del acceso completo al catálogo sin
    // convertir el documento de cortesía en una pieza de venta.
    expect($portada[1])
        ->not->toContain('Programas incluidos')
        ->toContain('En tu ruta')
        ->toContain($cursos.' cursos')
        ->toContain('15 h por semana');
    expect($html)->toContain('Empieza hoy');

    // Cortesía, no venta: sin botón de membresía.
    expect($html)->not->toContain('Quiero activar mi membresía');
});

it('marca en el inventario lo que ya está en el plan', function () {
    Queue::fake();

    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/final', redisenoCapturaFinal())
        ->assertSessionHasNoErrors();

    $ruta = LearningRoute::query()->latest('id')->firstOrFail();
    $html = $this->actingAs(FunnelFactory::author())->get('/rutas/'.$ruta->id.'/documento')->getContent();

    $enPlan = collect($ruta->snapshot['ruta']['cursos'])->where('capa', 'itinerario')->pluck('slug')->unique();
    $marcados = substr_count($html, '<span class="rf-inv-dot planned"></span>');

    expect($marcados)->toBeGreaterThan(0)->toBeLessThanOrEqual($enPlan->count());
});

it('exige que la vigencia de la membresía anual sea de un año', function (string $fin, bool $pasa) {
    Queue::fake();

    $respuesta = $this->actingAs(FunnelFactory::author())
        ->post('/rutas/final', redisenoCapturaFinal(['ends_on' => $fin]));

    $pasa
        ? $respuesta->assertSessionHasNoErrors()
        : $respuesta->assertSessionHasErrors('ends_on');
})->with([
    'el día antes del aniversario' => ['2027-09-30', true],
    'el aniversario' => ['2027-10-01', true],
    'seis meses' => ['2027-03-31', false],
    'trece meses' => ['2027-10-31', false],
]);

// ------------------------------------------------------- párrafos editables

it('propone los párrafos que el asesor puede ajustar, con el texto que se imprimiría', function () {
    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/propuesta', redisenoCaptura())
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rutas/propuesta')
            ->where('parrafos.0.slug', 'portada.ruta-enfocada')
            ->where('parrafos.1.slug', 'porque.con-contexto')
            ->where('parrafos.2.slug', 'objetivo.ruta-enfocada')
            ->where('parrafos.2.texto', fn (string $texto) => str_contains($texto, 'Para tu puesto de ingeniera de procesos')));
});

it('imprime el párrafo que el asesor ajustó y lo congela en el snapshot', function () {
    [$ruta, $html] = redisenoGancho(redisenoCaptura([
        'ajustes' => ['objetivo.ruta-enfocada' => '  Para ti, que llevas la línea de ensamble:   empieza por lo básico. '],
    ]));

    expect($ruta->snapshot['ajustes_copy'])->toBe([
        'objetivo.ruta-enfocada' => 'Para ti, que llevas la línea de ensamble: empieza por lo básico.',
    ]);
    expect($html)
        ->toContain('Para ti, que llevas la línea de ensamble: empieza por lo básico.')
        ->not->toContain('Para tu puesto de ingeniera de procesos');
});

it('no acepta un párrafo ajustado con una promesa de equivalencia o un precio', function (string $texto) {
    $this->actingAs(FunnelFactory::author())
        ->post('/rutas', redisenoCaptura(['ajustes' => ['objetivo.ruta-enfocada' => $texto]]))
        ->assertSessionHasErrors('ajustes.objetivo.ruta-enfocada');

    expect(LearningRoute::count())->toBe(0);
})->with([
    'equivalencia' => ['Esta ruta equivale a un Green Belt.'],
    'precio' => ['Todo por $4,199 al año.'],
    'lecciones' => ['Son 40 lecciones.'],
]);

it('solo deja ajustar los párrafos editables, y cortos', function () {
    $this->actingAs(FunnelFactory::author())
        ->post('/rutas', redisenoCaptura(['ajustes' => ['credencial.dc3' => 'Otra cosa']]))
        ->assertSessionHasErrors('ajustes');

    $this->actingAs(FunnelFactory::author())
        ->post('/rutas', redisenoCaptura(['ajustes' => ['objetivo.ruta-enfocada' => str_repeat('a', 221)]]))
        ->assertSessionHasErrors('ajustes.objetivo.ruta-enfocada');
});

it('el validador escanea lo ajustado aunque llegue por otro camino', function () {
    // La petición es la comodidad; la barrera es el validador. Un webhook que
    // emita sin pasar por el formulario no se salta el escaneo.
    $funnelRoute = FunnelFactory::route(['siete-herramientas-de-la-calidad', 'spc'], 'calidad');
    $lead = Lead::create(['name' => 'Rosa Elena', 'source' => 'formulario', 'advisor_id' => FunnelFactory::author()->id]);

    $result = app(RouteGenerator::class)->generate(RouteInput::fromFunnel(
        lead: $lead,
        documentType: DocumentType::where('slug', DocumentTypeSeeder::ENFOCADA)->sole(),
        funnelRoute: $funnelRoute,
        copyOverrides: ['objetivo.ruta-enfocada' => 'Esta ruta equivale a un Green Belt.'],
    ));

    expect($result->report->passes())->toBeFalse();
    expect($result->report->blockingFailures()->pluck('ruleCode'))->toContain('NO_CERT_EQUIVALENCE');
});

it('una corrección conserva lo ajustado si no lo vuelve a mandar', function () {
    [$ruta] = redisenoGancho(redisenoCaptura([
        'ajustes' => ['objetivo.ruta-enfocada' => 'Mi propio objetivo.'],
    ]));

    // Corrige el nombre sin tocar los párrafos.
    $this->actingAs(FunnelFactory::author())
        ->put('/rutas/'.$ruta->id, redisenoCaptura(['name' => 'Mariana Cruz López', 'funnel_id' => $ruta->funnel_id]))
        ->assertSessionHasNoErrors();

    expect($ruta->refresh()->snapshot['ajustes_copy'])->toBe(['objetivo.ruta-enfocada' => 'Mi propio objetivo.']);

    // Y los devuelve al catálogo si los manda vacíos.
    $this->actingAs(FunnelFactory::author())
        ->put('/rutas/'.$ruta->id, [...redisenoCaptura(['funnel_id' => $ruta->funnel_id]), 'ajustes' => ['objetivo.ruta-enfocada' => '']])
        ->assertSessionHasNoErrors();

    expect($ruta->refresh()->snapshot['ajustes_copy'])->toBe([]);
});
