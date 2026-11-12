<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\DocumentType;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LearningRoute;
use App\Services\Documents\EditableCopy;
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
        'area' => 'calidad',
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
        'area' => 'calidad',
        'funnel_id' => $overrides['funnel_id'] ?? redisenoEmbudo(['dmaic', 'siete-herramientas-de-la-calidad', 'spc'], 'sixsigma'),
        'context' => null,
        'plan' => 'expert_anual',
        'starts_on' => '2026-10-01',
        'ends_on' => '2027-09-30',
        'weekly_hours' => 18,
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

it('titula la portada con el nombre de la ruta consultada y nombra el curso por el que arranca', function () {
    [$ruta, $html] = redisenoGancho(redisenoCaptura());

    $primero = Course::where('slug', 'siete-herramientas-de-la-calidad')->sole()->name;

    expect($html)
        ->toContain('<h1 class="pt-h1">'.e('Ruta de prueba'))
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
        ->toContain('<h2>Quiénes somos</h2>')
        ->toContain('+80,000')
        ->toContain('Empresas que confían en SyS')
        ->toContain('img/dpsys/empresas/pfizer.png')
        ->toContain('img/dpsys/cofundadores/rosbel.jpg')
        ->toContain('img/dpsys/cofundadores/yussef.jpg');

    expect($html)->not->toContain('Reseña de Google');
});

it('presenta los cofundadores también cuando la ruta pertenece a otra especialidad', function () {
    [, $html] = redisenoGancho(redisenoCaptura([
        'funnel_id' => redisenoEmbudo(['excel-basico', 'excel-intermedio'], 'excel'),
    ]));

    expect($html)->toContain('Rosbel Salinas Reséndez')
        ->toContain('Yussef Salomón Quirarte')
        ->not->toContain('<div class="resenas">');
});

it('enlaza la semblanza y el registro de cada cofundador', function () {
    [, $html] = redisenoGancho(redisenoCaptura());

    foreach (config('dpsys.cofundadores') as $fundador) {
        expect($html)->toContain($fundador['perfil'])
            ->toContain('Buscar en STPS: '.$fundador['agente']);
    }

    expect($html)->toContain(config('dpsys.fuentes.stps'));
});

it('cierra con el acceso anual, igual que el audio', function () {
    [$ruta, $html] = redisenoGancho(redisenoCaptura());

    expect($html)->toContain('el tiempo que te quede de tus 12 meses de acceso');
    expect($ruta->snapshot['guion']['texto'])->toContain('durante 12 meses');
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
        ->toContain('18 h por semana');
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
            ->where('parrafos.2.texto', fn (string $texto) => str_contains($texto, 'Empieza por Ruta de prueba')));
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

it('aplica el lenguaje y la marca de la ruta principal sin exponer el cargo por separado', function () {
    [, $html] = redisenoGancho(redisenoCaptura(['occupation' => 'Ing.']));
    $texto = strip_tags($html);

    expect($html)->not->toContain('class="pt-ocup"')->not->toContain('class="avatar"');
    expect($texto)->toContain('Ruta principal personalizada')
        ->toContain('Preparada por DPSYS')
        ->toContain('Especialización principal')
        ->toContain('Cursos adicionales de refuerzo')
        ->toContain('Lo que te recomiendo')
        ->toContain('el tiempo que te quede de tus 12 meses de acceso')
        ->toContain('certificaciones')->toContain('facilidades de pago')
        ->not->toContain('para tu rol de')->not->toContain('Para tu puesto de')
        ->not->toContain('Core Tools en DP SyS son cuatro programas')
        ->not->toContain('Terminar esta ruta no te deja videos vistos');

    expect(strpos($html, 'Especialización principal'))->toBeLessThan(strpos($html, 'Cursos adicionales de refuerzo'));
    preg_match('#<div class="firma">(.*?)</div>\s*</div>#s', $html, $firma);
    expect($firma[0] ?? '')->toContain('logo-vino.svg')->toContain('Desarrollo profesional SyS')->not->toContain('Eje Cursos');
});

it('prepara el objetivo de la ruta inicial aun cuando no se conoce el grado ni la ocupación', function () {
    $copy = app(EditableCopy::class);
    expect($copy->default('objetivo.ruta-enfocada', ['ancla' => 'Lean Manufacturing']))
        ->toContain('Empieza por Lean Manufacturing')->not->toContain('{ocupacion}');
});

it('explica cada refuerzo a partir de su contenido y conserva el contexto del lead', function () {
    [$ruta, $html] = redisenoGancho(redisenoCaptura([
        'context' => 'Quiero reducir rechazos y analizar datos de calidad',
    ]));

    $adicionales = collect($ruta->snapshot['ruta']['cursos'])->where('rol', 'complementaria');
    expect($adicionales)->toHaveCount(3);
    preg_match_all('#<div class="cu-motivo">.*?<p>(.*?)</p>.*?</div>#s', $html, $motivos);
    expect($motivos[1])->toHaveCount(3);
    expect(array_unique($motivos[1]))->toHaveCount(3);
    expect($html)->toContain('Tomamos en cuenta lo que nos compartiste: «Quiero reducir rechazos y analizar datos de calidad»')
        ->toContain('Directrices para auditar sistemas de gestión')
        ->toContain('planear y ejecutar auditorías internas de calidad')
        ->toContain('resolver problemas de raíz con el método 8D');
});

it('reserva espacio para el contexto y mantiene los refuerzos juntos en una hoja de continuación', function () {
    [, $html] = redisenoGancho(redisenoCaptura([
        'occupation' => 'Coordinadora de calidad',
        'context' => 'Quiero mejorar el control de procesos, reducir desperdicios y desarrollar habilidades para liderar a mi equipo.',
    ]));

    preg_match_all('#<section class="hoja inicial-ruta">(.*?)</section>#s', $html, $hojas);

    expect($hojas[1])->toHaveCount(2);
    expect($hojas[1][0])->toContain('Especialización principal')->not->toContain('class="cu-motivo"');
    expect($hojas[1][1])->toContain('Tu ruta (2)')->toContain('Tomamos en cuenta lo que nos compartiste');
    expect(substr_count($hojas[1][1], 'class="cu-motivo"'))->toBe(3);

    foreach ($hojas[1] as $hoja) {
        expect($hoja)->toContain('class="enc"')->toContain('class="pie"');
    }
});

it('presenta una sola hoja institucional con respaldos verificables y redes oficiales', function () {
    [, $html] = redisenoGancho(redisenoCaptura());

    expect(substr_count($html, '<section class="hoja confianza-sys">'))->toBe(1);
    expect($html)->toContain('Desde 2018 impulsamos el desarrollo')
        ->toContain('Certificaciones y acreditaciones')
        ->toContain('alcance según el programa')
        ->toContain('cofundadores/rosbel.jpg')
        ->toContain('cofundadores/yussef.jpg')
        ->toContain('https://academy.dpsys.com.mx/egresados')
        ->toContain('https://www.facebook.com/DesarrolloprofesionalSyS/')
        ->toContain('https://www.instagram.com/desarrolloprofesionalsys/')
        ->toContain('https://www.linkedin.com/company/desarrollo-profesional-sys/')
        ->toContain('https://www.youtube.com/@desarrolloprofesionalsys973')
        ->toContain('https://www.tiktok.com/@dp.sys')
        ->toContain('material de apoyo para reforzar todo aprendizaje.')
        ->not->toContain('evaluaciones con retroalimentación.');

    foreach (config('dpsys.avales') as $aval) {
        expect($html)->toContain('https://drive.google.com/file/d/'.$aval['documento'].'/view');
    }
});
