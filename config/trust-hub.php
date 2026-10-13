<?php

return [

    /*
     * Orígenes desde los que el centro de confianza puede reportar eventos,
     * separados por coma. Vacío acepta cualquiera, que es lo cómodo en
     * desarrollo; en producción va el dominio donde se publique el sitio.
     */
    'origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUST_HUB_ORIGINS', '')),
    ))),

];
