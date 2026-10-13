<?php

use App\Http\Controllers\TrustHub\TrustEventController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

/*
 * El centro de confianza es un sitio estático que vive fuera de esta aplicación
 * (ver `trust-hub/`). Lo único que toca aquí es este receptor: el sitio le manda
 * cada apertura, sección vista y clic con `navigator.sendBeacon`.
 *
 * Va sin sesión ni CSRF porque quien lo llama es el navegador del lead desde otro
 * dominio, y con límite por IP porque es la única puerta pública que escribe.
 */
Route::post('hub/eventos', TrustEventController::class)
    ->withoutMiddleware([PreventRequestForgery::class])
    ->middleware('throttle:120,1')
    ->name('trust-hub.events');
