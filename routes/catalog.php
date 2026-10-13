<?php

use App\Http\Controllers\Catalog\CatalogDocumentController;
use Illuminate\Support\Facades\Route;

/*
 * El catálogo completo en PDF, decidido el 2026-09-25. El asesor lo descarga
 * desde la barra y se lo manda a cualquier lead; no es una ruta y no deja
 * renglón en la bandeja.
 */
Route::middleware(['auth'])->group(function () {
    Route::get('catalogo/pdf', [CatalogDocumentController::class, 'download'])->name('catalog.pdf');
});
