<?php

declare(strict_types=1);

/*
 * El módulo de KPIs de ventas.
 *
 * Los KPIs, sus medidas, los tipos de evento y las metas son datos: nada de eso
 * vive aquí. Esto es solo lo que depende del entorno.
 */

return [

    /*
     * «Hoy» para ventas es hoy en México, no en UTC: una venta de las 19:00
     * cuenta para el día en que se hizo, y el resumen diario sale a su hora.
     */
    'timezone' => env('KPI_TIMEZONE', 'America/Mexico_City'),

    /*
     * El tipo de evento al que el generador de rutas sugiere una venta cada vez
     * que se registra una membresía. Si no existe o se desactiva, no sugiere nada.
     * Los campos que llena, cuando el tipo los tiene: `plan`, `importe`, `cantidad`.
     */
    'membership_event_type' => env('KPI_MEMBERSHIP_EVENT_TYPE', 'venta'),

    /*
     * El resumen diario y el semanal van al grupo de ventas en Telegram
     * (decidido el 2026-09-26) y quedan también en el módulo. Sin token o sin
     * grupo, el resumen se guarda igual y el módulo dice que no se envió.
     */
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_KPI_CHAT_ID'),
    ],

    'digests' => [
        'daily_at' => env('KPI_DAILY_DIGEST_AT', '21:30'),
        // Lunes por la mañana, con la semana que acaba de cerrar.
        'weekly_at' => env('KPI_WEEKLY_DIGEST_AT', '08:30'),
    ],

];
