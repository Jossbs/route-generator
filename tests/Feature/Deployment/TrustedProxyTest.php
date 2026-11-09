<?php

declare(strict_types=1);

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

it('solo acepta HTTPS reenviado desde el proxy configurado', function (string $remote, bool $secure) {
    config(['trustedproxy.proxies' => ['192.0.2.10']]);
    $request = Request::create('http://rutas.example.test/up', server: [
        'REMOTE_ADDR' => $remote,
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ]);

    $result = (new TrustProxies)->handle($request, fn (Request $request): bool => $request->isSecure());

    expect($result)->toBe($secure);
})->with([
    'proxy confiable' => ['192.0.2.10', true],
    'cliente directo' => ['192.0.2.11', false],
]);
