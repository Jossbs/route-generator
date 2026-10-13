<?php

use App\Http\Controllers\Funnels\FunnelController;
use App\Http\Controllers\Funnels\FunnelDraftValidationController;
use App\Http\Controllers\Funnels\FunnelRouteController;
use Illuminate\Support\Facades\Route;

/*
 * El módulo de embudos.
 *
 * Las URL van en español porque las lee quien arma la promoción; los nombres de
 * ruta y las clases van en inglés, como el resto del repo. «Embudo» es lenguaje
 * interno: nunca aparece en nada que vea el lead.
 *
 * El grupo conserva `auth` aunque el módulo no tenga login: hoy lo satisface
 * `ResolveAdvisor`, que resuelve al asesor por la costura de identidad, y el día
 * que el sistema anfitrión traiga su propia sesión sigue siendo esto lo que
 * protege las rutas. Quitarlo ahora obligaría a volver a ponerlo después.
 */

Route::middleware(['auth'])->group(function () {
    Route::get('embudos', [FunnelController::class, 'index'])->name('funnels.index');
    Route::get('embudos/nuevo', [FunnelController::class, 'create'])->name('funnels.create');
    Route::post('embudos', [FunnelController::class, 'store'])->name('funnels.store');
    Route::get('embudos/{funnel}', [FunnelController::class, 'show'])->name('funnels.show');
    Route::get('embudos/{funnel}/editar', [FunnelController::class, 'edit'])->name('funnels.edit');
    Route::put('embudos/{funnel}', [FunnelController::class, 'update'])->name('funnels.update');
    Route::delete('embudos/{funnel}', [FunnelController::class, 'destroy'])->name('funnels.destroy');

    Route::post('embudos/{funnel}/rutas', [FunnelRouteController::class, 'store'])->name('funnel-routes.store');
    Route::put('embudos/{funnel}/rutas/{route}', [FunnelRouteController::class, 'update'])->name('funnel-routes.update');
    Route::delete('embudos/{funnel}/rutas/{route}', [FunnelRouteController::class, 'destroy'])->name('funnel-routes.destroy');

    // Una lectura sobre un borrador que todavía no se guarda: el panel del editor.
    // La primera es para el embudo que aún no existe, al crearlo.
    Route::post('embudos/validacion', [FunnelDraftValidationController::class, 'create'])
        ->name('funnels.validate-new');
    Route::post('embudos/{funnel}/validacion', [FunnelDraftValidationController::class, 'store'])
        ->name('funnels.validate');
});
