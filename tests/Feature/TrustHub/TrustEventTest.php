<?php

declare(strict_types=1);

use App\Enums\TrustEventType;
use App\Models\LearningRoute;
use App\Models\TrustEvent;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Testing\TestResponse;
use Tests\Support\FunnelFactory;

/*
 * El receptor del centro de confianza.
 *
 * El sitio vive en otro dominio y reporta con `sendBeacon`, así que aquí se
 * prueba lo que llega por ese camino: JSON crudo en un cuerpo `text/plain`, sin
 * sesión ni CSRF. Lo que importa es que la visita anónima se guarde y que el
 * token de la ruta inicial la ligue a su lead.
 */

/** Manda un evento como lo manda el navegador. */
function beacon(array $payload, array $headers = []): TestResponse
{
    return test()->call(
        'POST',
        '/hub/eventos',
        server: collect(['CONTENT_TYPE' => 'text/plain;charset=UTF-8'])
            ->merge(collect($headers)->mapWithKeys(fn ($v, $k) => ['HTTP_'.strtoupper(str_replace('-', '_', $k)) => $v]))
            ->all(),
        content: json_encode($payload),
    );
}

/** Una ruta inicial emitida, con su token. */
function rutaParaElHub(): LearningRoute
{
    test()->seed(DatabaseSeeder::class);

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

it('guarda una apertura anónima', function () {
    beacon([
        'e' => 'abierta',
        'v' => 'a1b2c3d4-visita',
        'a' => 'rosbel',
        'r' => 'https://wa.me/',
        'utm' => ['source' => 'whatsapp', 'medium' => null],
    ])->assertNoContent();

    $evento = TrustEvent::query()->sole();

    expect($evento->event)->toBe(TrustEventType::Abierta)
        ->and($evento->advisor_key)->toBe('rosbel')
        ->and($evento->lead_id)->toBeNull()
        ->and($evento->ip_hash)->toHaveLength(64)
        ->and($evento->meta['utm'])->toBe(['source' => 'whatsapp'])
        ->and($evento->meta['referrer'])->toBe('https://wa.me/');
});

it('liga la visita al lead cuando trae el token de su ruta inicial', function () {
    $ruta = rutaParaElHub();

    beacon([
        'e' => 'clic',
        'v' => 'a1b2c3d4-visita',
        't' => 'whatsapp:hero',
        'l' => $ruta->access_token,
    ])->assertNoContent();

    expect(TrustEvent::query()->sole())
        ->route_id->toBe($ruta->id)
        ->lead_id->toBe($ruta->lead_id)
        ->advisor_id->toBe($ruta->advisor_id)
        ->target->toBe('whatsapp:hero');
});

it('deja anónima la visita cuyo token no corresponde a ninguna ruta', function () {
    beacon(['e' => 'abierta', 'v' => 'a1b2c3d4-visita', 'l' => 'no-existe'])->assertNoContent();

    expect(TrustEvent::query()->sole()->route_id)->toBeNull();
});

it('rechaza lo que no es un evento del sitio', function (array $payload) {
    beacon($payload)->assertUnprocessable();

    expect(TrustEvent::query()->count())->toBe(0);
})->with([
    'evento desconocido' => [['e' => 'compra', 'v' => 'a1b2c3d4-visita']],
    'sin visita' => [['e' => 'abierta']],
    'destino con caracteres raros' => [['e' => 'clic', 'v' => 'a1b2c3d4-visita', 't' => '<script>']],
    'campo utm ajeno' => [['e' => 'abierta', 'v' => 'a1b2c3d4-visita', 'utm' => ['x' => 'y']]],
]);

it('con orígenes configurados, solo acepta esos', function () {
    config(['trust-hub.origins' => ['https://confianza.dpsys.com.mx']]);

    beacon(['e' => 'abierta', 'v' => 'a1b2c3d4-visita'], ['Origin' => 'https://otro.com'])->assertForbidden();
    beacon(['e' => 'abierta', 'v' => 'a1b2c3d4-visita'], ['Origin' => 'https://confianza.dpsys.com.mx'])->assertNoContent();

    expect(TrustEvent::query()->count())->toBe(1);
});

it('queda fuera de la protección contra falsificación, que en pruebas no corre', function () {
    // Laravel apaga esa protección al correr pruebas, así que la petición de
    // arriba pasaría aunque la ruta no la excluyera. Se afirma sobre la ruta.
    $ruta = app('router')->getRoutes()->getByName('trust-hub.events');

    expect($ruta->excludedMiddleware())->toContain(PreventRequestForgery::class);
});
