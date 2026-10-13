<?php

use App\Http\Controllers\Kpis\ClosureController;
use App\Http\Controllers\Kpis\DashboardController;
use App\Http\Controllers\Kpis\EventTypeController;
use App\Http\Controllers\Kpis\KpiDefinitionController;
use App\Http\Controllers\Kpis\ReportController;
use App\Http\Controllers\Kpis\SalesAssignmentController;
use App\Http\Controllers\Kpis\SalesEventController;
use App\Http\Controllers\Kpis\SalesTeamController;
use App\Http\Controllers\Kpis\SettingsController;
use App\Http\Controllers\Kpis\TargetController;
use Illuminate\Support\Facades\Route;

/*
 * El módulo de KPIs de ventas, decidido el 2026-09-26.
 *
 * El tablero y sus reportes los ve todo ventas: el ranking es abierto. Capturar
 * eventos, fijar metas, cerrar meses y configurar KPIs es del administrador
 * (`manage-kpis`), que en el MVP es quien captura todo.
 */
Route::middleware(['auth'])->prefix('kpis')->name('kpis.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('reporte.csv', [ReportController::class, 'csv'])->name('report.csv');
    Route::get('reporte.pdf', [ReportController::class, 'pdf'])->name('report.pdf');

    Route::middleware('can:manage-kpis')->group(function () {
        Route::get('eventos', [SalesEventController::class, 'index'])->name('events.index');
        Route::get('eventos.csv', [SalesEventController::class, 'export'])->name('events.export');
        Route::post('eventos', [SalesEventController::class, 'store'])->name('events.store');
        Route::put('eventos/{event}', [SalesEventController::class, 'update'])->name('events.update');
        Route::post('eventos/{event}/anular', [SalesEventController::class, 'void'])->name('events.void');
        Route::post('eventos/{event}/confirmar', [SalesEventController::class, 'confirm'])->name('events.confirm');
        Route::post('eventos/{event}/descartar', [SalesEventController::class, 'discard'])->name('events.discard');

        Route::get('metas', [TargetController::class, 'index'])->name('targets.index');
        Route::post('metas', [TargetController::class, 'store'])->name('targets.store');

        Route::get('cierres', [ClosureController::class, 'index'])->name('closures.index');
        Route::post('cierres', [ClosureController::class, 'store'])->name('closures.store');
        Route::post('resumenes', [ClosureController::class, 'digest'])->name('digests.store');

        Route::get('configuracion', [SettingsController::class, 'index'])->name('settings');
        Route::post('configuracion/tipos', [EventTypeController::class, 'store'])->name('event-types.store');
        Route::put('configuracion/tipos/{eventType}', [EventTypeController::class, 'update'])->name('event-types.update');
        Route::post('configuracion/kpis', [KpiDefinitionController::class, 'store'])->name('definitions.store');
        Route::put('configuracion/kpis/{kpi}', [KpiDefinitionController::class, 'update'])->name('definitions.update');
        Route::post('configuracion/equipos', [SalesTeamController::class, 'store'])->name('teams.store');
        Route::put('configuracion/equipos/{team}', [SalesTeamController::class, 'update'])->name('teams.update');
        Route::post('configuracion/asignaciones', [SalesAssignmentController::class, 'store'])->name('assignments.store');
        Route::put('configuracion/asignaciones/{assignment}', [SalesAssignmentController::class, 'update'])->name('assignments.update');
    });
});
