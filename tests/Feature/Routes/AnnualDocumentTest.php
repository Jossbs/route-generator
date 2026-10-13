<?php

declare(strict_types=1);

use App\Enums\RouteStatus;
use App\Models\Course;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LearningRoute;
use App\Models\Membership;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FunnelFactory;

/*
 * La ruta final: el documento que se descarga, ya cerrada la membresía.
 *
 * Documento aparte y captura aparte. Pide lo mismo que la inicial más la vigencia,
 * que va impresa y ordena los doce meses: «sin ellas no se genera».
 */

/**
 * La ruta curada por la que preguntó el lead.
 *
 * El anual no se compone de sus cursos —recorre el catálogo completo en doce
 * meses— pero su especialidad abre el mes 1, para que el miembro sienta
 * continuidad con el documento que ya recibió.
 */
function embudoFinal(): int
{
    // Una sola por prueba: el gancho y el anual del mismo lead salen de la misma
    // ruta curada, como en la conversación real.
    return Funnel::query()->value('id')
        ?? FunnelFactory::available(
            ['dmaic', 'siete-herramientas-de-la-calidad', 'spc'],
            'sixsigma',
        )->funnel_id;
}

function capturaFinal(array $overrides = []): array
{
    return [
        'full_name' => 'Mariana Cruz Aguilar',
        'phone' => '4421185502',
        'occupation' => 'Coordinadora de calidad',
        'funnel_id' => embudoFinal(),
        'context' => null,
        'plan' => 'expert_anual',
        // Lo que se cobró. Se asienta en la membresía y nunca llega al documento.
        'price' => '4199.00',
        'starts_on' => '2026-10-01',
        'ends_on' => '2027-09-30',
        // A 15 h por semana cabe el itinerario completo (746 h en 783): las
        // pruebas que no son del ritmo siguen viendo el año entero.
        'weekly_hours' => 15,
        ...$overrides,
    ];
}

function anualEmitido(): LearningRoute
{
    return LearningRoute::query()
        ->whereHas('documentType', fn ($q) => $q->where('slug', DocumentTypeSeeder::ANUAL))
        ->sole();
}

it('arma los doce meses antes de generar nada', function () {
    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/final/meses', capturaFinal())
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rutas/meses')
            ->has('bloques')
            ->has('totales')
            ->has('curso_ids'));

    expect(LearningRoute::count())->toBe(0);
    expect(Membership::count())->toBe(0);
});

it('exige la vigencia, que va impresa y ordena los meses', function () {
    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/final/meses', capturaFinal(['starts_on' => '', 'ends_on' => '']))
        ->assertSessionHasErrors(['starts_on', 'ends_on']);
});

it('no acepta una vigencia que termina antes de empezar', function () {
    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/final', capturaFinal(['ends_on' => '2026-09-01']))
        ->assertSessionHasErrors('ends_on');
});

it('emite el anual con su membresía y sin enlace', function () {
    Queue::fake();

    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/final', capturaFinal())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $anual = anualEmitido();

    expect($anual->status)->toBe(RouteStatus::Validated);
    expect($anual->membership_id)->toBe(Membership::sole()->id);
    // Se descarga, no se entrega por enlace: no emite token.
    expect($anual->access_token)->toBeNull();
    expect($anual->lead->status)->toBe('miembro');
});

it('liga el anual con el gancho que lo originó', function () {
    Queue::fake();
    $advisor = FunnelFactory::author();

    $this->actingAs($advisor)->post('/rutas', [
        'name' => 'Mariana Cruz Aguilar',
        'phone' => '4421185502',
        'occupation' => 'Coordinadora de calidad',
        'funnel_id' => embudoFinal(),
    ]);

    $gancho = LearningRoute::sole();

    $this->actingAs($advisor)->post('/rutas/final', capturaFinal());

    expect(anualEmitido()->parent_route_id)->toBe($gancho->id);
});

it('lleva las dos capas: lo recomendado y lo incluido', function () {
    Queue::fake();

    $this->actingAs(FunnelFactory::author())->post('/rutas/final', capturaFinal());

    $capas = collect(anualEmitido()->snapshot['ruta']['cursos'])->pluck('capa')->unique();

    expect($capas)->toContain('itinerario');
    expect($capas)->toContain('inventario');
});

it('imprime la portada con los datos de la membresía', function () {
    Queue::fake();

    $this->actingAs(FunnelFactory::author())->post('/rutas/final', capturaFinal());

    $this->actingAs(FunnelFactory::author())
        ->get('/rutas/'.anualEmitido()->id.'/documento')
        ->assertOk()
        ->assertSee('Mariana Cruz Aguilar', escape: false)
        ->assertSee('Coordinadora de calidad', escape: false)
        ->assertSee('Tu membresía', escape: false)
        // El plan que el asesor eligió, no el respaldo genérico: se capturaba, se
        // guardaba y no llegaba nunca a la portada.
        ->assertSee('Expert Anual', escape: false)
        ->assertDontSee('Membresía anual', escape: false)
        // Y la vigencia escrita como se escribe. Salía en ISO al lado de una fecha
        // de emisión que sí decía «17 de septiembre de 2026».
        ->assertSee('30 de septiembre de 2027', escape: false)
        ->assertDontSee('2027-09-30', escape: false);
});

it('asienta lo que se cobró sin dejarlo llegar al documento', function () {
    Queue::fake();

    $this->actingAs(FunnelFactory::author())->post('/rutas/final', capturaFinal());

    $ruta = anualEmitido();

    // El importe existe: una membresía asentada sin decir por cuánto se cerró deja
    // ese dato viviendo solo en la conversación de WhatsApp del asesor.
    expect($ruta->membership->price)->toBe('4199.00');
    expect($ruta->membership->planLabel())->toBe('Expert Anual');

    // Y no viaja al snapshot, que es lo que alimenta al PDF. Lo que el payload no
    // carga, la plantilla no puede imprimir por descuido (docs/ARQUITECTURA.md §4.5).
    expect($ruta->snapshot['membresia'])->not->toHaveKey('price');
    expect(json_encode($ruta->snapshot))->not->toContain('4199');

    $html = $this->actingAs(FunnelFactory::author())
        ->get('/rutas/'.$ruta->id.'/documento')
        ->getContent();

    expect($html)->not->toContain('4199');
});

it('exige el plan y el importe al cerrar la membresía', function () {
    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/final', capturaFinal(['plan' => '', 'price' => '']))
        ->assertSessionHasErrors(['plan', 'price']);

    // Un plan fuera de la lista publicada se escribe, pero se escribe: si no, el
    // documento imprimiría «Otro» en la portada del miembro.
    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/final', capturaFinal(['plan' => 'otro', 'plan_other' => '']))
        ->assertSessionHasErrors('plan_other');
});

it('no habla de lecciones ni imprime precios en el anual', function () {
    Queue::fake();

    $this->actingAs(FunnelFactory::author())->post('/rutas/final', capturaFinal());

    $html = $this->actingAs(FunnelFactory::author())
        ->get('/rutas/'.anualEmitido()->id.'/documento')
        ->getContent();

    expect(strtolower($html))->not->toContain('lecciones');
    expect($html)->not->toContain('MXN');
    expect(strtolower($html))->not->toContain('core tools completo');
});

/*
 * Lo que el asesor ajusta en la pantalla de los meses.
 *
 * La pantalla siempre devuelve la lista que quedó, así que este es el camino
 * normal y no un caso raro. Lo que el asesor manda es una poda del itinerario, no
 * un documento nuevo: quitar un curso no puede costar los doce meses ni el
 * inventario de la membresía, que son las dos cosas que hacen anual a este
 * documento.
 */

/** @return list<int> Los cursos que la pantalla de los meses propone. */
function mesesPropuestos(array $captura): array
{
    $ids = [];

    test()->actingAs(FunnelFactory::author())
        ->post('/rutas/final/meses', $captura)
        ->assertInertia(function (AssertableInertia $page) use (&$ids): void {
            $ids = $page->toArray()['props']['curso_ids'];
        });

    return $ids;
}

it('propone solo el itinerario, no el inventario de la membresía', function () {
    $captura = capturaFinal();

    // El inventario es el catálogo entero. Si se propusiera aquí, el asesor
    // creería que quitarle un renglón se lo quita al miembro, y además tendría
    // que revisar 64 cursos para ajustar un itinerario que no los lleva todos.
    expect(count(mesesPropuestos($captura)))
        ->toBeLessThan(Course::query()->where('is_active', true)->count());

    // El mes encabeza la lectura desde el primer bloque: es lo que la pantalla
    // se llama.
    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/final/meses', $captura)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('bloques.0.mes', 1)
            ->etc());
});

it('conserva los doce meses y el inventario cuando el asesor ajusta la lista', function () {
    Queue::fake();
    $captura = capturaFinal();
    $propuestos = mesesPropuestos($captura);

    // El asesor quita uno, como en la pantalla real.
    $elegidos = array_slice($propuestos, 0, count($propuestos) - 1);

    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/final', [...$captura, 'course_ids' => $elegidos])
        ->assertSessionHasNoErrors();

    $cursos = collect(anualEmitido()->snapshot['ruta']['cursos']);
    $itinerario = $cursos->where('capa', 'itinerario');

    expect($cursos->pluck('capa')->unique())->toContain('inventario');
    expect($itinerario->pluck('mes_sugerido')->filter()->unique())->not->toBeEmpty();
    expect($itinerario->pluck('mes_sugerido')->max())->toBeLessThanOrEqual(12);
    // Lo que el asesor quitó no vuelve por su cuenta al itinerario.
    expect($itinerario->count())->toBeLessThanOrEqual(count($elegidos));
});

it('reconoce al lead del gancho en vez de darlo de alta otra vez', function () {
    Queue::fake();
    $advisor = FunnelFactory::author();

    $this->actingAs($advisor)->post('/rutas', [
        'name' => 'Mariana Cruz',
        'phone' => '4421185502',
        'occupation' => 'Coordinadora de calidad',
        'funnel_id' => embudoFinal(),
    ])->assertSessionHasNoErrors();

    $this->actingAs($advisor)->post('/rutas/final', capturaFinal())
        ->assertSessionHasNoErrors();

    expect(Lead::count())->toBe(1);
    expect(Lead::sole()->status)->toBe('miembro');
    expect(anualEmitido()->parent_route_id)->not->toBeNull();
});

it('exige el WhatsApp, que es con lo que se reconoce al lead', function () {
    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/final', capturaFinal(['phone' => '']))
        ->assertSessionHasErrors('phone');

    expect(LearningRoute::count())->toBe(0);
});

it('no acepta cursos que no existen', function () {
    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/final', capturaFinal(['course_ids' => [999999]]))
        ->assertSessionHasErrors('course_ids.0');

    expect(LearningRoute::count())->toBe(0);
});

it('imprime los doce meses aunque el asesor haya ajustado la lista', function () {
    Queue::fake();
    $captura = capturaFinal();
    $propuestos = mesesPropuestos($captura);

    $this->actingAs(FunnelFactory::author())->post('/rutas/final', [
        ...$captura,
        'course_ids' => array_slice($propuestos, 0, count($propuestos) - 1),
    ]);

    $html = $this->actingAs(FunnelFactory::author())
        ->get('/rutas/'.anualEmitido()->id.'/documento')
        ->assertOk()
        ->getContent();

    // La ruta anual conserva los doce meses, incluidos los meses sin cursos, y
    // también imprime el inventario completo de la membresía.
    expect($html)
        ->toContain('Tu ruta, mes a mes')
        ->toContain('Todo lo que tienes abierto');

    preg_match_all('#<div class="rf-mes(?: |")#', $html, $meses);
    expect($meses[0])->toHaveCount(12);
});

/*
 * El orden de los doce meses es del asesor (decidido el 2026-09-22).
 *
 * Antes el asesor solo podaba y el motor reordenaba al generar; además, la
 * pantalla trataba todo el itinerario anual como si fuera la ruta curada del
 * gancho, y la X no quitaba nada. Ahora la secuencia que manda es la que sale:
 * el motor solo decide dónde termina cada mes.
 */

/** @return array<string, mixed> Las props de la pantalla de los meses. */
function mesesCon(array $captura): array
{
    $props = [];

    test()->actingAs(FunnelFactory::author())
        ->post('/rutas/final/meses', $captura)
        ->assertInertia(function (AssertableInertia $page) use (&$props): void {
            $props = $page->toArray()['props'];
        });

    return $props;
}

it('deja quitar cualquier curso de los doce meses', function () {
    $cursos = collect(mesesCon(capturaFinal())['bloques'])->flatMap(fn (array $b): array => $b['cursos']);

    expect($cursos)->not->toBeEmpty();
    expect($cursos->pluck('fijo')->unique()->all())->toBe([false]);
});

it('respeta el orden que dejó el asesor, en la pantalla y en el documento', function () {
    Queue::fake();
    $captura = capturaFinal();
    $alReves = array_reverse(mesesPropuestos($captura));

    expect(mesesCon([...$captura, 'course_ids' => $alReves])['curso_ids'])->toBe($alReves);

    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/final', [...$captura, 'course_ids' => $alReves])
        ->assertSessionHasNoErrors();

    expect(anualEmitido()->itineraryCourseIds())->toBe($alReves);
});

it('bloquea un curso puesto antes de su requisito y ofrece el arreglo', function () {
    $captura = capturaFinal();
    $propuestos = mesesPropuestos($captura);
    [$pide, $requisito] = [$propuestos[0], $propuestos[1]];

    // Ningún par del universo anual tiene hoy un requisito duro: los niveles se
    // condensan en su paquete. Se siembra uno para que el orden importe.
    DB::table('course_prerequisites')->insert([
        'course_id' => $pide,
        'requires_course_id' => $requisito,
        'strength' => 'duro',
    ]);

    $bloqueo = collect(mesesCon([...$captura, 'course_ids' => $propuestos])['bloqueos'])
        ->firstWhere('codigo', 'LEVEL_SEQUENCE');

    expect($bloqueo['arreglo'])->toBe([['mover' => $requisito, 'antes_de' => $pide]]);

    // El arreglo aplicado: el requisito justo antes del curso que lo pide.
    $acomodados = [$requisito, $pide, ...array_slice($propuestos, 2)];

    expect(collect(mesesCon([...$captura, 'course_ids' => $acomodados])['bloqueos'])->pluck('codigo'))
        ->not->toContain('LEVEL_SEQUENCE');
});

/*
 * El ritmo del miembro (decidido el 2026-09-22).
 *
 * El asesor pregunta cuántas horas a la semana puede estudiar el lead. De ahí
 * sale cuántos cursos caben en su año —elegidos por su perfil, empezando por lo
 * que pidió— y dónde termina cada mes. El documento no promete un año que no
 * cabe: si la lista se pasa, no se genera.
 */

it('pide las horas por semana, que es lo que decide cuántos cursos caben', function () {
    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/final/meses', capturaFinal(['weekly_hours' => null]))
        ->assertSessionHasErrors('weekly_hours');

    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/final/meses', capturaFinal(['weekly_hours' => 41]))
        ->assertSessionHasErrors('weekly_hours');
});

it('a pocas horas propone menos cursos, que caben en su año, y empieza por lo que pidió', function () {
    $holgado = mesesCon(capturaFinal(['weekly_hours' => 15]));
    $justo = mesesCon(capturaFinal(['weekly_hours' => 3]));

    expect(count($justo['curso_ids']))->toBeLessThan(count($holgado['curso_ids']));
    expect($justo['ritmo']['horas'])->toBeLessThanOrEqual($justo['ritmo']['capacidad']);
    expect($justo['bloqueos'])->toBe([]);

    // La especialidad del embudo abre el año aunque el tiempo no alcance para todo.
    expect($justo['bloques'][0]['abre'])->toBeTrue();
    expect($justo['bloques'][0]['mes'])->toBe(1);
});

it('la portada y la meta anual cuentan lo recomendado, sin prometer todo el inventario', function () {
    Queue::fake();
    $captura = capturaFinal(['weekly_hours' => 3]);
    $propuesta = mesesCon($captura);

    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/final', [...$captura, 'course_ids' => $propuesta['curso_ids']])
        ->assertSessionHasNoErrors();

    $html = $this->get('/rutas/'.anualEmitido()->id.'/documento')->assertOk()->getContent();
    $cursos = count($propuesta['curso_ids']);
    $horas = $propuesta['ritmo']['horas'];

    expect($html)
        ->toContain($cursos.' cursos · '.$horas.' h')
        ->toContain($cursos.' cursos · '.$horas.' horas · '.$cursos.' constancias')
        ->not->toContain('64 cursos · 746 h')
        ->toContain('Todo lo que tienes abierto');
    expect(anualEmitido()->snapshot['ruta']['totales']['cursos'])->toBe(64);
});

it('no deja generar un año que no cabe en sus horas', function () {
    Queue::fake();
    $holgado = mesesCon(capturaFinal(['weekly_hours' => 15]))['curso_ids'];
    $captura = capturaFinal(['weekly_hours' => 3, 'course_ids' => $holgado]);

    $pantalla = mesesCon($captura);

    expect($pantalla['ritmo']['horas'])->toBeGreaterThan($pantalla['ritmo']['capacidad']);
    expect(collect($pantalla['bloqueos'])->pluck('codigo'))->toContain('ANNUAL_FITS_PACE');

    $this->actingAs(FunnelFactory::author())->post('/rutas/final', $captura);

    expect(anualEmitido()->status)->toBe(RouteStatus::Rejected);
    Queue::assertNothingPushed();
});

it('reparte un curso largo en los meses que abarca a su ritmo', function () {
    Queue::fake();
    $captura = capturaFinal(['weekly_hours' => 3]);
    $propuestos = mesesPropuestos($captura);

    // El curso más largo de la propuesta: a 3 h por semana, cualquiera de más de
    // 13 h cruza la orilla del mes.
    $largo = Course::query()->whereIn('id', $propuestos)->orderByDesc('duration_hours')->first();
    expect($largo->duration_hours)->toBeGreaterThan(13);

    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/final', [...$captura, 'course_ids' => $propuestos])
        ->assertSessionHasNoErrors();

    $renglon = collect(anualEmitido()->snapshot['ruta']['cursos'])
        ->where('capa', 'itinerario')
        ->firstWhere('slug', $largo->slug);

    expect($renglon['mes_fin'])->toBeGreaterThan($renglon['mes_sugerido']);

    $html = $this->actingAs(FunnelFactory::author())
        ->get('/rutas/'.anualEmitido()->id.'/documento')
        ->getContent();

    // La tarjeta reaparece en cada mes que abarca y señala la continuación.
    expect(substr_count($html, '<b>'.$renglon['nombre'].'</b>'))
        ->toBe($renglon['mes_fin'] - $renglon['mes_sugerido'] + 1);
    expect($html)->toContain('Continúa · lo terminas este mes');
    expect($html)->toContain('3 h por semana');
    expect($html)->not->toContain('15 h por semana');
});

it('guarda los datos escritos como se escriben, sin importar cómo se capturaron', function () {
    Queue::fake();

    $this->actingAs(FunnelFactory::author())->post('/rutas/final', capturaFinal([
        'full_name' => 'MARIANA CRUZ DE LA O',
        'occupation' => 'COORDINADORA DE CALIDAD',
        'phone' => '+52 442 118 55 02',
    ]))->assertSessionHasNoErrors();

    $membresia = Membership::sole();

    expect($membresia->full_name)->toBe('Mariana Cruz de la O');
    expect($membresia->occupation)->toBe('Coordinadora de calidad');
    expect(Lead::sole()->phone)->toBe('+524421185502');
});

it('reconoce al lead aunque el teléfono se haya escrito distinto en el gancho', function () {
    Queue::fake();
    $advisor = FunnelFactory::author();

    $this->actingAs($advisor)->post('/rutas', [
        'name' => 'Mariana Cruz',
        'phone' => '442 118 55 02',
        'occupation' => 'Coordinadora de calidad',
        'funnel_id' => embudoFinal(),
    ])->assertSessionHasNoErrors();

    $this->actingAs($advisor)->post('/rutas/final', capturaFinal(['phone' => '4421185502']))
        ->assertSessionHasNoErrors();

    expect(Lead::count())->toBe(1);
});

it('no acepta un WhatsApp que no son diez dígitos', function (string $telefono) {
    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/final', capturaFinal(['phone' => $telefono]))
        ->assertSessionHasErrors('phone');
})->with(['442118550', '44211855021', 'sin número']);

it('acepta cualquier número de horas entre 1 y 40, no solo los de 5 en 5', function () {
    Queue::fake();

    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/final', capturaFinal(['weekly_hours' => 7]))
        ->assertSessionHasNoErrors();

    expect(Membership::sole()->weekly_hours)->toBe(7);
});
