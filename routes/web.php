<?php

/*
 * El módulo se inserta en un sistema que ya tiene su propio acceso, así que aquí
 * no hay login ni registro ni pantallas de cuenta: la identidad del asesor entra
 * por `App\Services\Identity\CurrentAdvisor`. La raíz es la captura de la ruta,
 * definida en `learning-routes.php`.
 */

require __DIR__.'/funnels.php';
require __DIR__.'/learning-routes.php';
require __DIR__.'/trust-hub.php';
require __DIR__.'/catalog.php';
