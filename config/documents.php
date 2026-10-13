<?php

declare(strict_types=1);

/*
 * Cómo se materializa el documento.
 *
 * El PDF es el producto: es lo que el asesor adjunta en Kommo y lo que el lead
 * guarda en su teléfono. Por eso el render es un archivo de verdad y no una
 * página que alguien tiene que acordarse de imprimir.
 *
 * Lo produce Chromium headless dentro de Gotenberg, que corre como contenedor
 * aparte (ver compose.yaml). La alternativa aprobada era Browsershot; se eligió
 * Gotenberg porque no obliga a instalar Node ni Chromium en la máquina de nadie,
 * y porque en producción es el mismo contenedor que en desarrollo.
 */

return [

    /*
     * `gotenberg` produce el archivo; `pagina` solo cierra el ciclo de estados y
     * deja que el asesor imprima desde el navegador. Las pruebas corren en
     * `pagina`: una suite que dependa de un contenedor levantado deja de correr
     * en la máquina de quien no lo tenga.
     */
    'renderer' => env('DOCUMENT_RENDERER', 'gotenberg'),

    'gotenberg' => [
        'url' => env('GOTENBERG_URL', 'http://localhost:3001'),

        /*
         * Un documento anual son ocho hojas con fuentes incrustadas. Chromium
         * tarda segundos, no milisegundos, y por eso el render vive en la cola.
         */
        'timeout' => (int) env('GOTENBERG_TIMEOUT', 60),
    ],

    /*
     * Disco privado: el PDF lleva el nombre y la ocupación del lead, así que se
     * sirve por la ruta de descarga —que pasa por `auth`— y nunca por una URL
     * pública que alguien pueda adivinar.
     */
    'disk' => env('DOCUMENT_DISK', 'local'),

];
