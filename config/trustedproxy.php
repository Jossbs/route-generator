<?php

return [
    // IPs o CIDR del proxy que termina HTTPS, separados por coma.
    'proxies' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('TRUSTED_PROXIES', '')),
    ))),
];
