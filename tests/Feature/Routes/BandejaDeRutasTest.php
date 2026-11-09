<?php

declare(strict_types=1);

use App\Enums\RouteStatus;
use App\Models\Funnel;
use App\Models\LearningRoute;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FunnelFactory;

/*
 * La bandeja: lo que ya salió.
 *
 * El módulo nació sin ella porque el asesor viene a generar, no a consultar. Pero
 * un documento que se manda y no se puede volver a encontrar obliga a generarlo
 * otra vez, y entonces el mismo lead recibe dos rutas distintas del mismo asesor.
 *
 * Cuatro datos por renglón, que son las cuatro preguntas que se hacen al abrirla:
 * para quién, qué documento, quién lo hizo y cuándo. El estado no está: decía lo
 * mismo en todos los renglones. En su lugar va cuántas veces se corrigió.
 */

function embudoDeBandeja(): int
{
    return Funnel::query()->value('id')
        ?? FunnelFactory::available(
            ['siete-herramientas-de-la-calidad', 'spc', 'amef'],
            'calidad',
        )->funnel_id;
}

function emitirPara(string $nombre, ?User $asesor = null): LearningRoute
{
    $embudo = embudoDeBandeja();

    test()->actingAs($asesor ?? FunnelFactory::author())->post('/rutas', [
        'name' => $nombre,
        'occupation' => 'Ingeniera de procesos',
        'area' => 'calidad',
        'funnel_id' => $embudo,
    ]);

    return LearningRoute::query()->latest('id')->firstOrFail();
}

beforeEach(function () {
    Queue::fake();
});

it('lista las rutas con para quién, qué documento, quién y cuándo', function () {
    $ruta = emitirPara('Mariana Cruz');

    $this->actingAs(FunnelFactory::author())
        ->get('/rutas')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rutas/index')
            ->has('rutas.data', 1)
            ->where('rutas.data.0.id', $ruta->lead_id)
            ->where('rutas.data.0.lead', 'Mariana Cruz')
            ->where('rutas.data.0.inicial.id', $ruta->id)
            ->where('rutas.data.0.inicial.documento', 'Ruta inicial')
            ->where('rutas.data.0.inicial.revisiones', 0)
            ->has('rutas.data.0.inicial.creada_en')
            ->where('rutas.data.0.final', null)
            ->where('rutas.data.0.asesor', FunnelFactory::author()->name)
            ->has('periodos')
            ->has('etapas')
            ->has('asesores')
            ->has('especialidades'));
});

it('muestra primero la última que salió', function () {
    emitirPara('Primera');
    emitirPara('Segunda');

    $this->actingAs(FunnelFactory::author())
        ->get('/rutas')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rutas.data.0.lead', 'Segunda')
            ->where('rutas.data.1.lead', 'Primera')
            ->etc());
});

it('encuentra al lead por su nombre', function () {
    emitirPara('Mariana Cruz');
    emitirPara('Jorge Medina');

    $this->actingAs(FunnelFactory::author())
        ->get('/rutas?q=medina')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rutas.data', 1)
            ->where('rutas.data.0.lead', 'Jorge Medina')
            ->etc());
});

it('no ofrece el estado, que decía lo mismo en todos los renglones', function () {
    // La columna y su filtro salieron el 2026-09-17: una ruta se emite y se
    // renderiza en el mismo minuto, así que la respuesta era «Lista» siempre. El
    // campo sigue en la base —lo necesitan la cola y el enlace público— y la ficha
    // sí lo dice.
    emitirPara('Mariana Cruz');

    $this->actingAs(FunnelFactory::author())
        ->get('/rutas')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('estados')
            ->missing('rutas.data.0.estado')
            ->missing('filtros.estado')
            ->etc());
});

it('separa lo que armó cada asesor', function () {
    // Hay más de una persona generando documentos y cada una vuelve a buscar lo
    // suyo: sin este filtro, la bandeja del lunes es la de todos revuelta.
    $otro = User::query()->create([
        'name' => 'Auxiliar de ventas',
        'email' => 'auxiliar@dpsys.com.mx',
        'password' => 'irrelevante-para-la-prueba',
    ]);

    emitirPara('De la asesora');
    emitirPara('Del auxiliar', $otro);

    $this->actingAs($otro)
        ->get('/rutas?asesor='.$otro->id)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rutas.data', 1)
            ->where('rutas.data.0.lead', 'Del Auxiliar')
            ->etc());
});

/*
 * Un renglón por lead, decidido el 2026-09-23.
 *
 * La inicial y la final son dos momentos de la misma persona. Listadas por
 * separado, el lead aparecía dos veces justo el día que compró; juntas, lo que
 * falta se ve: una final pendiente es un lead que todavía no cierra.
 */

function emitirFinalPara(string $nombre, string $telefono): LearningRoute
{
    test()->actingAs(FunnelFactory::author())->post('/rutas/final', [
        'full_name' => $nombre,
        'phone' => $telefono,
        'occupation' => 'Coordinadora de calidad',
        'area' => 'calidad',
        'funnel_id' => embudoDeBandeja(),
        'plan' => 'expert_anual',
        'price' => '4199.00',
        'starts_on' => '2026-10-01',
        'ends_on' => '2027-09-30',
        'weekly_hours' => 18,
    ])->assertSessionHasNoErrors();

    return LearningRoute::query()->latest('id')->firstOrFail();
}

function emitirInicialCon(string $nombre, string $telefono): LearningRoute
{
    test()->actingAs(FunnelFactory::author())->post('/rutas', [
        'name' => $nombre,
        'phone' => $telefono,
        'occupation' => 'Coordinadora de calidad',
        'area' => 'calidad',
        'funnel_id' => embudoDeBandeja(),
    ])->assertSessionHasNoErrors();

    return LearningRoute::query()->latest('id')->firstOrFail();
}

it('junta la ruta inicial y la final del mismo lead en un solo renglón', function () {
    $inicial = emitirInicialCon('Mariana Cruz Aguilar', '4421185502');
    $final = emitirFinalPara('Mariana Cruz Aguilar', '4421185502');

    expect($final->lead_id)->toBe($inicial->lead_id);

    $this->actingAs(FunnelFactory::author())
        ->get('/rutas')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rutas.data', 1)
            ->where('rutas.data.0.inicial.id', $inicial->id)
            ->where('rutas.data.0.final.id', $final->id)
            ->where('rutas.data.0.vigencia.desde', '2026-10-01')
            ->where('rutas.data.0.para_final', null)
            ->etc());
});

it('ofrece la final prellenada al lead que todavía no la tiene', function () {
    emitirInicialCon('Mariana Cruz Aguilar', '4421185502');

    $this->actingAs(FunnelFactory::author())
        ->get('/rutas')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rutas.data.0.final', null)
            ->where('rutas.data.0.para_final.tipo', 'final')
            ->where('rutas.data.0.para_final.nombre', 'Mariana Cruz Aguilar')
            ->where('rutas.data.0.para_final.telefono', '+524421185502')
            ->has('rutas.data.0.para_final.embudo')
            ->etc());
});

it('separa a quien espera su final de quien ya tiene membresía', function () {
    emitirInicialCon('Mariana Cruz Aguilar', '4421185502');
    emitirFinalPara('Mariana Cruz Aguilar', '4421185502');
    emitirPara('Jorge Medina');

    $this->actingAs(FunnelFactory::author())
        ->get('/rutas?etapa=esperando_final')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rutas.data', 1)
            ->where('rutas.data.0.lead', 'Jorge Medina')
            ->etc());

    $this->actingAs(FunnelFactory::author())
        ->get('/rutas?etapa=con_membresia')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rutas.data', 1)
            ->where('rutas.data.0.lead', 'Mariana Cruz Aguilar')
            ->etc());
});

it('filtra por el periodo en que salió o se corrigió', function () {
    $this->travelTo(now()->subMonth()->startOfMonth()->addDays(3));
    emitirPara('Del mes pasado');
    $this->travelBack();

    emitirPara('De hoy');

    $this->actingAs(FunnelFactory::author())
        ->get('/rutas?periodo=hoy')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rutas.data', 1)
            ->where('rutas.data.0.lead', 'De Hoy')
            ->etc());

    $this->actingAs(FunnelFactory::author())
        ->get('/rutas?periodo=mes_pasado')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rutas.data', 1)
            ->where('rutas.data.0.lead', 'Del Mes Pasado')
            ->etc());

    $this->actingAs(FunnelFactory::author())
        ->get('/rutas?periodo=personalizado&desde='.now()->toDateString())
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rutas.data', 1)
            ->where('filtros.desde', now()->toDateString())
            ->etc());
});

it('ignora un periodo o una fecha que no reconoce', function () {
    emitirPara('Mariana Cruz');

    $this->actingAs(FunnelFactory::author())
        ->get('/rutas?periodo=siglo&desde=ayer')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rutas.data', 1)
            ->where('filtros.periodo', '')
            ->where('filtros.desde', '')
            ->etc());
});

it('filtra por la especialidad del lead', function () {
    $ruta = emitirPara('Mariana Cruz');

    $this->actingAs(FunnelFactory::author())
        ->get('/rutas?especialidad='.$ruta->anchor_specialization_id)
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rutas.data', 1)->etc());

    $this->actingAs(FunnelFactory::author())
        ->get('/rutas?especialidad=999999')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rutas.data', 0)->etc());
});

it('pagina de quince en quince', function () {
    foreach (range(1, 16) as $n) {
        emitirPara("Lead {$n}");
    }

    $this->actingAs(FunnelFactory::author())
        ->get('/rutas')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rutas.data', 15)
            ->where('rutas.meta.total', 16)
            ->where('rutas.meta.last_page', 2)
            ->etc());

    $this->actingAs(FunnelFactory::author())
        ->get('/rutas?page=2')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rutas.data', 1)
            ->where('rutas.meta.from', 16)
            ->etc());
});

it('liga la final al gancho aunque este se haya capturado sin teléfono', function () {
    // El caso que la bandeja destapó con datos reales: el gancho sale sin
    // teléfono o con el nombre corto, la final sí lo lleva completo, y el
    // teléfono ya no alcanza para reencontrar al lead. El botón de la bandeja
    // manda el lead, y la final cae sobre él.
    $gancho = emitirPara('Joseline Benavides');
    $gancho->update(['status' => RouteStatus::Rendered]);

    test()->actingAs(FunnelFactory::author())->post('/rutas/final', [
        'lead_id' => $gancho->lead_id,
        'full_name' => 'Joseline Sarahi Benavides Serrano',
        'phone' => '4421185502',
        'occupation' => 'Coordinadora de calidad',
        'area' => 'calidad',
        'funnel_id' => embudoDeBandeja(),
        'plan' => 'expert_anual',
        'price' => '4199.00',
        'starts_on' => '2026-10-01',
        'ends_on' => '2027-09-30',
        'weekly_hours' => 18,
    ])->assertSessionHasNoErrors();

    $final = LearningRoute::query()->latest('id')->firstOrFail();

    expect($final->lead_id)->toBe($gancho->lead_id);
    // El gancho ya estaba renderizado cuando el lead compró, que es lo normal.
    expect($final->parent_route_id)->toBe($gancho->id);

    $this->actingAs(FunnelFactory::author())
        ->get('/rutas')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rutas.data', 1)
            ->where('rutas.data.0.inicial.id', $gancho->id)
            ->where('rutas.data.0.final.id', $final->id)
            ->etc());
});
