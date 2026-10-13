<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // No hay pantalla de acceso a la que mandar a nadie: el módulo se inserta
        // en un sistema que ya tiene la suya. Si el guard no resolvió asesor, es
        // que falta sembrarlo o que el anfitrión no lo entregó, y eso se dice
        // explícito en vez de redirigir a una ruta que no existe.
        $middleware->redirectGuestsTo(fn () => abort(
            503,
            'No hay ningún asesor que resolver. En desarrollo corre `php artisan db:seed --class=AdvisorSeeder`; en producción conecta la identidad del sistema anfitrión mediante CurrentAdvisor.',
        ));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
