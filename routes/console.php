<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Los resúmenes de KPIs al grupo de ventas en Telegram (decidido el 2026-09-26):
 * el del día al cierre de la jornada y el de la semana el lunes por la mañana.
 * Van en la hora de ventas, no en la del servidor.
 */
Schedule::command('kpis:resumen diario')
    ->dailyAt((string) config('kpis.digests.daily_at'))
    ->timezone((string) config('kpis.timezone'));

Schedule::command('kpis:resumen semanal')
    ->weeklyOn(1, (string) config('kpis.digests.weekly_at'))
    ->timezone((string) config('kpis.timezone'));
