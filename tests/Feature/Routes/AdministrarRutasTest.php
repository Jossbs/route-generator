<?php

declare(strict_types=1);

use App\Enums\RouteStatus;
use App\Models\Course;
use App\Models\Funnel;
use App\Models\LearningRoute;
use App\Models\Membership;
use App\Models\RouteValidation;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FunnelFactory;

/*
 * La bandeja administra: corregir y borrar lo que ya salió.
 *
 * Antes solo se podía consultar, y un nombre mal escrito obligaba a emitir un
 * documento nuevo: el mismo lead terminaba con dos rutas distintas del mismo
 * asesor, que es justo lo que la bandeja vino a evitar.
 *
 * Corregir pisa el snapshot anterior —un renglón por documento, no una cadena de
 * versiones— y deja contado cuántas veces se rehízo. Borrar es suave: se va lo que
 * está de cara al lead, se quedan las validaciones y la bitácora, que son la
 * prueba de que las restricciones de docs/ARQUITECTURA.md §4 se evaluaron antes de mandar algo.
 */

/**
 * El embudo con el que se emiten las rutas de estas pruebas.
 *
 * Uno solo por prueba: corregir una ruta la deja apuntando al mismo embudo del
 * que salió, así que armar otro en la misma prueba no representa nada real.
 */
function embudoAdmin(): int
{
    return Funnel::query()->value('id')
        ?? FunnelFactory::available(
            ['siete-herramientas-de-la-calidad', 'spc', 'amef'],
            'calidad',
        )->funnel_id;
}

/** @param array<string, mixed> $overrides */
function capturaAdmin(array $overrides = []): array
{
    return [
        'name' => 'Mariana Cruz',
        'phone' => '4421185502',
        'occupation' => 'Ingeniera de procesos',
        'area' => 'calidad',
        'funnel_id' => embudoAdmin(),
        ...$overrides,
    ];
}

/** Una ruta inicial ya emitida, con su PDF en el disco. */
function rutaParaCorregir(?User $asesor = null): LearningRoute
{
    test()->actingAs($asesor ?? FunnelFactory::author())
        ->post('/rutas', capturaAdmin());

    return LearningRoute::query()->latest('id')->sole();
}

beforeEach(function () {
    Storage::fake('local');
});

it('abre la corrección con lo que el documento ya decía', function () {
    $ruta = rutaParaCorregir();

    $this->actingAs(FunnelFactory::author())
        ->get("/rutas/{$ruta->id}/editar")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rutas/generar')
            ->where('tipo', 'inicial')
            ->where('precargado.nombre', 'Mariana Cruz')
            ->where('precargado.phone', '4421185502')
            ->where('precargado.occupation', 'Ingeniera de procesos')
            ->where('precargado.funnel_id', $ruta->funnel_id)
            ->where('editando.id', $ruta->id)
            ->where('editando.revisiones', 0));
});

it('vuelve a mostrar los cursos que la ruta ya llevaba, sin recomendar de nuevo', function () {
    // Corregir un nombre no es rearmar la ruta: el asesor ya decidió qué cursos
    // van, y volver a proponer aquí le devolvería los que había quitado.
    $ruta = rutaParaCorregir();
    $antes = $ruta->itineraryCourseIds();

    $this->actingAs(FunnelFactory::author())
        ->post("/rutas/{$ruta->id}/propuesta", capturaAdmin(['name' => 'Mariana Cruz Aguilar']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rutas/propuesta')
            ->where('curso_ids', $antes)
            ->where('editando.id', $ruta->id));
});

it('corrige sobre el mismo renglón y cuenta la corrección', function () {
    $ruta = rutaParaCorregir();

    $this->actingAs(FunnelFactory::author())
        ->put("/rutas/{$ruta->id}", capturaAdmin([
            'name' => 'Mariana Cruz Aguilar',
            'occupation' => 'Jefa de calidad',
        ]))
        ->assertRedirect(route('routes.show', $ruta))
        ->assertSessionHasNoErrors();

    // Un renglón, no dos: la bandeja lleva un documento por ruta.
    expect(LearningRoute::count())->toBe(1);

    $ruta->refresh();

    expect($ruta->revisions)->toBe(1)
        ->and($ruta->revised_at)->not->toBeNull()
        ->and($ruta->lead->name)->toBe('Mariana Cruz Aguilar')
        ->and($ruta->lead->occupation)->toBe('Jefa de calidad')
        // El snapshot es el del documento corregido y sigue sellado.
        ->and($ruta->snapshotIsIntact())->toBeTrue()
        // Y el documento se volvió a renderizar: la cola corre en `sync` en
        // pruebas, así que la corrección llega hasta el archivo.
        ->and($ruta->status)->toBe(RouteStatus::Rendered);
});

it('conserva el enlace que el lead ya podría tener', function () {
    // El enlace pudo salir en el chat desde el primer envío: lo que tiene que
    // abrir es el documento corregido, no el anterior.
    $ruta = rutaParaCorregir();
    $token = $ruta->access_token;

    $this->actingAs(FunnelFactory::author())
        ->put("/rutas/{$ruta->id}", capturaAdmin(['name' => 'Mariana Cruz Aguilar']));

    expect($ruta->refresh()->access_token)->toBe($token);

    $this->get("/r/{$token}")->assertOk()->assertSee('Mariana Cruz Aguilar', false);
});

it('rehace el archivo en vez de dejar el anterior', function () {
    // El PDF de al lado describe un snapshot que ya no existe. Dejarlo ahí es
    // dejar que se mande: la ficha solo mira `pdf_path`.
    $ruta = rutaParaCorregir();
    $ruta->forceFill(['pdf_path' => 'documentos/'.$ruta->id.'/ruta-enfocada.pdf'])->save();
    Storage::disk('local')->put($ruta->pdf_path, '%PDF-1.7 el de antes');

    $anterior = $ruta->pdf_path;

    $this->actingAs(FunnelFactory::author())
        ->put("/rutas/{$ruta->id}", capturaAdmin(['name' => 'Mariana Cruz Aguilar']));

    Storage::disk('local')->assertMissing($anterior);
});

it('no deja quitar los cursos de la campaña al corregir', function () {
    // El embudo es inamovible también aquí (docs/ARQUITECTURA.md §8): una petición recortada
    // se completa antes de emitir, en vez de prometer la rama a medias.
    $ruta = rutaParaCorregir();
    $curados = $ruta->funnelRoute->orderedCourseIds();

    $this->actingAs(FunnelFactory::author())
        ->put("/rutas/{$ruta->id}", capturaAdmin([
            'course_ids' => [$curados[0]],
        ]));

    expect($ruta->refresh()->itineraryCourseIds())
        ->toContain(...$curados);
});

it('permite corregir la ruta por la que preguntó el lead', function () {
    $ruta = rutaParaCorregir();
    $alternativa = FunnelFactory::available(
        ['dmaic', 'siete-herramientas-de-la-calidad', 'spc'],
        'sixsigma',
        ['slug' => 'alternativa-six-sigma'],
    );

    $this->actingAs(FunnelFactory::author())
        ->put("/rutas/{$ruta->id}", capturaAdmin([
            'funnel_id' => $alternativa->funnel_id,
            'course_ids' => $alternativa->orderedCourseIds(),
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($ruta->refresh()->funnel_id)->toBe($alternativa->funnel_id);
});

it('corrige la ruta final sobre la misma membresía', function () {
    $embudo = embudoAdmin();

    $this->actingAs(FunnelFactory::author())->post('/rutas/final', [
        'full_name' => 'Mariana Cruz Aguilar',
        'phone' => '4421185502',
        'occupation' => 'Coordinadora de calidad',
        'area' => 'calidad',
        'funnel_id' => $embudo,
        'plan' => 'expert_anual',
        'price' => '4199.00',
        'starts_on' => '2026-10-01',
        'ends_on' => '2027-09-30',
        'weekly_hours' => 18,
    ]);

    $anual = LearningRoute::query()->latest('id')->sole();
    // Una membresía anterior puede conservar un importe que el formulario ya no pide.
    $anual->membership->update(['price' => '4199.00']);

    $this->actingAs(FunnelFactory::author())->put("/rutas/{$anual->id}/final", [
        'full_name' => 'Mariana Cruz Aguilar',
        'phone' => '4421185502',
        'occupation' => 'Gerente de calidad',
        'area' => 'calidad',
        'funnel_id' => $embudo,
        'plan' => 'expert_anual',
        // La vigencia que le dictaron mal: va impresa y ordena los doce meses.
        'starts_on' => '2026-11-01',
        'ends_on' => '2027-10-31',
        'weekly_hours' => 18,
    ])->assertRedirect()->assertSessionHasNoErrors();

    // La venta fue una: el expediente del miembro no debe contarla dos veces.
    expect(Membership::count())->toBe(1);

    $membresia = Membership::sole();

    expect($membresia->starts_on->toDateString())->toBe('2026-11-01')
        ->and($membresia->occupation)->toBe('Gerente de calidad')
        ->and($membresia->price)->toBe('4199.00')
        ->and($anual->refresh()->revisions)->toBe(1)
        ->and($anual->membership_id)->toBe($membresia->id);
});

it('abre la corrección del anual con la membresía asentada', function () {
    // La vigencia y el plan van impresos: si la captura no los trae ya puestos, el
    // asesor los vuelve a teclear y la corrección introduce el error que venía a
    // arreglar.
    $embudo = embudoAdmin();

    $this->actingAs(FunnelFactory::author())->post('/rutas/final', [
        'full_name' => 'Mariana Cruz Aguilar',
        'phone' => '4421185502',
        'occupation' => 'Coordinadora de calidad',
        'area' => 'calidad',
        'funnel_id' => $embudo,
        'plan' => 'expert_anual',
        'price' => '4199.00',
        'starts_on' => '2026-10-01',
        'ends_on' => '2027-09-30',
        'weekly_hours' => 18,
    ]);

    $anual = LearningRoute::query()->latest('id')->sole();

    $this->actingAs(FunnelFactory::author())
        ->get("/rutas/{$anual->id}/editar")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rutas/generar')
            ->where('tipo', 'final')
            ->where('precargado.nombre', 'Mariana Cruz Aguilar')
            ->where('precargado.occupation', 'Coordinadora de calidad')
            ->where('precargado.plan', 'expert_anual')
            ->where('precargado.desde', '2026-10-01')
            ->where('precargado.hasta', '2027-09-30')
            ->where('editando.id', $anual->id));
});

it('no corrige un documento con el formulario del otro', function () {
    // Cada documento pide campos distintos. Corregir el anual por la puerta del
    // gancho emitiría un documento sin la vigencia, que va impresa.
    $ruta = rutaParaCorregir();

    $this->actingAs(FunnelFactory::author())
        ->put("/rutas/{$ruta->id}/final", [
            'full_name' => 'Mariana Cruz Aguilar',
            'phone' => '4421185502',
            'occupation' => 'Ingeniera de procesos',
            'area' => 'calidad',
            'funnel_id' => $ruta->funnel_id,
            'plan' => 'expert_anual',
            'price' => '4199.00',
            'starts_on' => '2026-10-01',
            'ends_on' => '2027-09-30',
            'weekly_hours' => 18,
        ])
        ->assertNotFound();

    expect($ruta->refresh()->revisions)->toBe(0);
});

it('conserva el documento anterior si una corrección tiene conflictos', function () {
    $ruta = rutaParaCorregir();
    $snapshot = $ruta->snapshot;
    $estado = $ruta->status;

    // Los tres cursos de liderazgo se traslapan: recomendar dos juntos ya es lo
    // que LEADERSHIP_OVERLAP impide, y una ruta detenida no llega al PDF. Van dos
    // y no tres porque ese es el techo de recomendados desde el 2026-09-22.
    $liderazgo = Course::query()
        ->whereIn('slug', [
            'liderazgo-para-ingenieros',
            'liderazgo-para-lideres-y-supervisores',
            'liderazgo-efectivo',
        ])
        ->pluck('id')
        ->all();

    expect($liderazgo)->toHaveCount(3);

    $this->actingAs(FunnelFactory::author())
        ->put("/rutas/{$ruta->id}", capturaAdmin([
            'course_ids' => [...$ruta->funnelRoute->orderedCourseIds(), ...array_slice($liderazgo, 0, 2)],
        ]))->assertSessionHasErrors('course_ids');

    $ruta->refresh();

    expect($ruta->status)->toBe($estado)
        ->and($ruta->snapshot)->toEqual($snapshot)
        ->and($ruta->revisions)->toBe(0);
});

it('borra la ruta de la bandeja y del disco, y deja el expediente', function () {
    $ruta = rutaParaCorregir();
    $ruta->forceFill(['pdf_path' => 'documentos/'.$ruta->id.'/ruta-enfocada.pdf'])->save();
    Storage::disk('local')->put($ruta->pdf_path, '%PDF-1.7 fingido');

    $archivo = $ruta->pdf_path;
    $token = $ruta->access_token;

    $this->actingAs(FunnelFactory::author())
        ->delete("/rutas/{$ruta->id}")
        ->assertRedirect(route('routes.index'));

    Storage::disk('local')->assertMissing($archivo);

    // Fuera de la bandeja y de su ficha.
    $this->actingAs(FunnelFactory::author())
        ->get('/rutas')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('rutas.data', 0)->etc());

    $this->actingAs(FunnelFactory::author())->get("/rutas/{$ruta->id}")->assertNotFound();

    // Y el enlace deja de abrir: es lo único que estaba de cara al lead.
    $this->get("/r/{$token}")->assertNotFound();

    // Lo que se queda: la prueba de que se validó y de que se generó.
    expect(LearningRoute::withTrashed()->count())->toBe(1)
        ->and(RouteValidation::where('route_id', $ruta->id)->exists())->toBeTrue()
        ->and(UsageEvent::where('route_id', $ruta->id)->exists())->toBeTrue();
});
