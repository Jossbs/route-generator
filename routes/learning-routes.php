<?php

use App\Http\Controllers\Routes\AnnualRouteController;
use App\Http\Controllers\Routes\LearningRouteController;
use App\Http\Controllers\Routes\PublicRouteController;
use App\Http\Controllers\Routes\RouteDocumentController;
use App\Http\Controllers\Routes\RouteInboxController;
use Illuminate\Support\Facades\Route;

/*
 * El módulo de rutas formativas.
 *
 * La raíz es la captura: el asesor entra a generar el documento de un lead que
 * tiene enfrente, que es su trabajo de todos los días. Un solo formulario cubre
 * los dos documentos —`?tipo=final` lo abre en la ruta final— y cada uno conserva
 * su paso de en medio, que no se salta: «nadie ve nada todavía, tú revisas antes
 * de que salga».
 *
 * `rutas` es la bandeja: lo que ya salió, para volver a encontrarlo sin generarlo
 * otra vez. Va un paso adentro y no en la raíz a propósito; consultar es lo que se
 * hace de vez en cuando, generar es lo que se hace todo el día.
 *
 * La entrega es el PDF —`rutas/{route}/pdf`, que es lo que se adjunta en Kommo—.
 * El enlace sigue existiendo debajo, por si el archivo pesa para el chat, y es lo
 * único que da señal de vuelta: `/r/{token}` es lo único fuera de `auth`, porque
 * quien lo abre es el lead.
 */

Route::middleware(['auth'])->group(function () {
    Route::get('/', [LearningRouteController::class, 'create'])->name('routes.create');

    Route::post('rutas/propuesta', [LearningRouteController::class, 'propose'])->name('routes.propose');
    Route::post('rutas', [LearningRouteController::class, 'store'])->name('routes.store');

    Route::post('rutas/final/meses', [AnnualRouteController::class, 'propose'])->name('routes.final.propose');
    Route::post('rutas/final', [AnnualRouteController::class, 'store'])->name('routes.final.store');

    Route::get('rutas', [RouteInboxController::class, 'index'])->name('routes.index');

    /*
     * La bandeja administra: además de encontrar lo que ya salió, corrige y borra.
     * Corregir es volver a emitir sobre el mismo renglón —el snapshot se pisa y
     * `revisions` cuenta las veces— y cada documento se corrige por el controlador
     * que lo emite, porque los campos que pide no son los mismos. Borrar es suave:
     * el renglón sale de la bandeja y el enlace deja de abrir, pero las
     * validaciones y la bitácora se quedan.
     */
    Route::get('rutas/{route}/editar', [LearningRouteController::class, 'edit'])->name('routes.edit');
    Route::post('rutas/{route}/propuesta', [LearningRouteController::class, 'repropose'])->name('routes.repropose');
    Route::put('rutas/{route}', [LearningRouteController::class, 'update'])->name('routes.update');
    Route::delete('rutas/{route}', [LearningRouteController::class, 'destroy'])->name('routes.destroy');

    Route::post('rutas/{route}/final/meses', [AnnualRouteController::class, 'repropose'])->name('routes.final.repropose');
    Route::put('rutas/{route}/final', [AnnualRouteController::class, 'update'])->name('routes.final.update');

    Route::get('rutas/{route}', [LearningRouteController::class, 'show'])->name('routes.show');
    Route::get('rutas/{route}/documento', [RouteDocumentController::class, 'show'])->name('routes.document');
    Route::get('rutas/{route}/pdf', [RouteDocumentController::class, 'download'])->name('routes.pdf');
    Route::post('rutas/{route}/pdf', [RouteDocumentController::class, 'rerender'])->name('routes.pdf.retry');
});

Route::get('r/{token}', [PublicRouteController::class, 'show'])->name('routes.public');
