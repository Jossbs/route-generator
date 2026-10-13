<?php

declare(strict_types=1);

use App\Enums\RouteStatus;
use App\Enums\UsageEventType;
use App\Jobs\RenderRouteDocument;
use App\Models\LearningRoute;
use App\Services\Documents\DocumentRenderer;
use App\Services\Documents\GotenbergRenderer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FunnelFactory;

/*
 * El PDF es la entrega.
 *
 * El asesor lo adjunta en la conversación de Kommo, así que tiene que existir
 * como archivo —no como una página que alguien se acuerde de imprimir— y llegar
 * con un nombre que se reconozca entre los veinte que ese teléfono ya tiene.
 *
 * Lo produce Chromium headless dentro de Gotenberg. Aquí se finge la respuesta
 * del contenedor: lo que se prueba es la costura —qué se le manda, dónde queda el
 * archivo y en qué estado deja a la ruta—, no que Chromium sepa maquetar.
 */

/** Una ruta emitida y validada, lista para renderizarse. */
function rutaEmitida(): LearningRoute
{
    $embudo = FunnelFactory::available(
        ['siete-herramientas-de-la-calidad', 'spc', 'amef'],
        'calidad',
    );

    test()->actingAs(FunnelFactory::author())->post('/rutas', [
        'name' => 'Mariana Cruz',
        'phone' => '4421185502',
        'occupation' => 'Ingeniera de procesos',
        'funnel_id' => $embudo->funnel_id,
    ]);

    return LearningRoute::query()->latest('id')->sole();
}

beforeEach(function () {
    config(['documents.renderer' => 'gotenberg']);
    Storage::fake('local');
});

it('guarda el PDF que devuelve el contenedor y deja la ruta lista', function () {
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fingido')]);

    $ruta = rutaEmitida();

    // La emisión ya despachó el render —la cola corre en `sync` en pruebas— así
    // que basta con recargar para ver en qué quedó.
    $ruta->refresh();

    expect($ruta->status)->toBe(RouteStatus::Rendered)
        ->and($ruta->pdf_path)->not->toBeNull()
        ->and($ruta->rendered_at)->not->toBeNull();

    Storage::disk('local')->assertExists($ruta->pdf_path);
});

it('manda el documento con sus fuentes dentro, no una URL que el contenedor tendría que alcanzar', function () {
    // Gotenberg corre aparte y no tiene por qué ver a la aplicación. Si el HTML
    // pidiera sus fuentes por red, el PDF saldría en Arial y nadie se enteraría
    // hasta tenerlo en el teléfono del lead.
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fingido')]);

    rutaEmitida();

    Http::assertSent(function ($request): bool {
        $cuerpo = $request->body();

        return str_contains($cuerpo, 'data:font/woff2;base64,')
            && ! str_contains($cuerpo, "url('".config('app.url').'/fonts/');
    });
});

it('entrega el archivo con el nombre del lead y del documento', function () {
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fingido')]);

    $ruta = rutaEmitida();

    $respuesta = $this->actingAs(FunnelFactory::author())->get("/rutas/{$ruta->id}/pdf");

    $respuesta->assertOk();
    expect($respuesta->headers->get('content-disposition'))
        ->toContain('Mariana Cruz')
        ->toContain('.pdf');
});

it('no ofrece un archivo que no existe', function () {
    // El render pudo fallar. Un 404 es la verdad; un archivo vacío con nombre
    // correcto llegaría al lead.
    Http::fake(['*' => Http::response('', 500)]);

    $ruta = rutaEmitida();

    expect($ruta->refresh()->status)->toBe(RouteStatus::Failed);

    $this->actingAs(FunnelFactory::author())
        ->get("/rutas/{$ruta->id}/pdf")
        ->assertNotFound();
});

it('deja que la ficha sepa si el archivo ya está y si acaba de generarse', function () {
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fingido')]);

    $ruta = rutaEmitida();

    // Se llega redirigido desde la emisión, así que la ficha trae el aviso que
    // dispara la descarga sola.
    $this->actingAs(FunnelFactory::author())
        ->withSession(['descargar' => true])
        ->get("/rutas/{$ruta->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rutas/detalle')
            ->where('pdf.listo', true)
            ->where('autoDescarga', true)
            ->etc());
});

it('vuelve a pedir el archivo cuando el render falló', function () {
    // El render depende de un contenedor que puede no estar. Sin reintento, la
    // única salida sería emitir otra vez el mismo documento y dejar dos rutas
    // para el mismo lead.
    // El contenedor falla la primera vez y responde la segunda, que es el caso
    // real: se cayó, alguien lo levantó y el asesor vuelve a pedir su archivo.
    Http::fake(['*/forms/chromium/convert/html' => Http::sequence()
        ->push('', 500)
        ->push('%PDF-1.7 fingido')]);

    $ruta = rutaEmitida();

    expect($ruta->refresh()->status)->toBe(RouteStatus::Failed);

    $this->actingAs(FunnelFactory::author())
        ->post("/rutas/{$ruta->id}/pdf")
        ->assertRedirect();

    expect($ruta->refresh()->status)->toBe(RouteStatus::Rendered)
        ->and($ruta->pdf_path)->not->toBeNull();
});

it('no reintenta una ruta que el validador detuvo', function () {
    // No falló el render: falló la ruta. Reintentarla entregaría justo lo que la
    // restricción impidió entregar.
    Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fingido')]);

    $ruta = rutaEmitida();
    $ruta->forceFill(['status' => RouteStatus::Rejected])->save();

    $this->actingAs(FunnelFactory::author())
        ->post("/rutas/{$ruta->id}/pdf")
        ->assertNotFound();
});

it('no vuelve a renderizar una ruta que el validador detuvo', function () {
    Http::fake();

    $ruta = rutaEmitida();
    $ruta->forceFill(['status' => RouteStatus::Rejected])->save();

    (new RenderRouteDocument($ruta->id))->handle(app(DocumentRenderer::class));

    expect($ruta->refresh()->status)->toBe(RouteStatus::Rejected);
});

it('rechaza una respuesta exitosa que no contiene un PDF', function (string $body) {
    Http::fake(['*' => Http::response($body)]);

    expect(fn () => app(GotenbergRenderer::class)->convert('<html></html>'))
        ->toThrow(RuntimeException::class, 'no devolvió un archivo PDF válido');
})->with(['', '<html>Error del servicio</html>', '{"error":"no PDF"}']);

it('no marca listo un archivo que no se pudo guardar', function () {
    Http::fake(['*' => Http::response('%PDF-1.7 fingido')]);
    $ruta = rutaEmitida();
    $ruta->forceFill(['status' => RouteStatus::Validated, 'pdf_path' => null])->save();
    Storage::disk('local')->deleteDirectory('documentos/'.$ruta->id);
    Storage::shouldReceive('disk')->with('local')->andReturn($disk = Mockery::mock());
    $disk->shouldReceive('put')->once()->andReturn(false);

    expect(fn () => (new RenderRouteDocument($ruta->id, $ruta->snapshot_hash))
        ->handle(app(DocumentRenderer::class)))
        ->toThrow(RuntimeException::class, 'No se pudo guardar');

    expect($ruta->refresh()->status)->toBe(RouteStatus::Validated)
        ->and($ruta->pdf_path)->toBeNull();
});

it('un trabajo antiguo no renderiza ni marca fallida una corrección nueva', function () {
    Http::fake(['*' => Http::response('%PDF-1.7 fingido')]);
    $ruta = rutaEmitida();
    $viejo = new RenderRouteDocument($ruta->id, $ruta->snapshot_hash);
    $ruta->forceFill(['status' => RouteStatus::Validated, 'snapshot_hash' => str_repeat('a', 64)])->save();
    Http::fake();

    $viejo->handle(app(DocumentRenderer::class));
    $viejo->failed(new RuntimeException('Falló el trabajo viejo'));

    expect($ruta->refresh()->status)->toBe(RouteStatus::Validated);
    Http::assertNothingSent();
});

it('un trabajo duplicado no vuelve a emitir el PDF ni cambia su estado al fallar', function () {
    Http::fake(['*' => Http::response('%PDF-1.7 fingido')]);
    $ruta = rutaEmitida();
    $job = new RenderRouteDocument($ruta->id, $ruta->snapshot_hash);
    Http::fake();

    $job->handle(app(DocumentRenderer::class));
    $job->failed(new RuntimeException('Fallo tardío'));

    expect($ruta->refresh()->status)->toBe(RouteStatus::Rendered)
        ->and($ruta->usageEvents()->where('event', UsageEventType::Renderizada)->count())->toBe(1);
    Http::assertNothingSent();
});

it('un error transitorio permite que la cola vuelva a intentar el render', function () {
    Http::fake(['*' => Http::sequence()
        ->push('%PDF-1.7 fingido')
        ->push('', 500)
        ->push('%PDF-1.7 reintentado')]);
    $ruta = rutaEmitida();
    $ruta->forceFill(['status' => RouteStatus::Validated, 'pdf_path' => null])->save();
    $job = new RenderRouteDocument($ruta->id, $ruta->snapshot_hash);

    expect(fn () => $job->handle(app(DocumentRenderer::class)))->toThrow(RuntimeException::class);
    expect($ruta->refresh()->status)->toBe(RouteStatus::Validated);

    $job->handle(app(DocumentRenderer::class));

    expect($ruta->refresh()->status)->toBe(RouteStatus::Rendered);
});

it('no renderiza un snapshot alterado después de validarlo', function () {
    Http::fake(['*' => Http::response('%PDF-1.7 fingido')]);
    $ruta = rutaEmitida();
    $ruta->forceFill(['status' => RouteStatus::Validated, 'snapshot' => ['alterado' => true]])->save();
    Http::fake();

    expect(fn () => (new RenderRouteDocument($ruta->id, $ruta->snapshot_hash))
        ->handle(app(DocumentRenderer::class)))
        ->toThrow(RuntimeException::class, 'snapshot cambió');
    Http::assertNothingSent();
});

it('no expone el documento imprimible de una ruta rechazada', function () {
    Http::fake(['*' => Http::response('%PDF-1.7 fingido')]);
    $ruta = rutaEmitida();
    $ruta->forceFill(['status' => RouteStatus::Rejected])->save();

    $this->actingAs(FunnelFactory::author())->get("/rutas/{$ruta->id}/documento")->assertNotFound();
    $this->get("/rutas/{$ruta->id}/pdf")->assertNotFound();
});

it('mantiene el enlace de respaldo si falla la conversión del PDF validado', function () {
    Http::fake(['*' => Http::response('', 500)]);
    $ruta = rutaEmitida();

    expect($ruta->refresh()->status)->toBe(RouteStatus::Failed);
    $this->get('/r/'.$ruta->access_token)->assertOk()->assertSee('Mariana Cruz');
});

it('un trabajo de una revisión anterior no toca la nueva aunque el contenido sea idéntico', function () {
    Http::fake(['*' => Http::response('%PDF-1.7 fingido')]);
    $ruta = rutaEmitida();
    $job = new RenderRouteDocument($ruta->id, $ruta->snapshot_hash, $ruta->revisions);
    $ruta->forceFill(['status' => RouteStatus::Validated, 'revisions' => $ruta->revisions + 1])->save();
    Http::fake();

    $job->handle(app(DocumentRenderer::class));
    $job->failed(new RuntimeException('Falló la revisión anterior'));

    expect($ruta->refresh()->status)->toBe(RouteStatus::Validated);
    Http::assertNothingSent();
});
