<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Dormido. Acompaña a `funnel_routes.choose_n`: cuando regrese la mecánica «elige
 * N de M», un renglón `elegible` será candidato y uno `fijo` irá siempre. En la v1
 * todo renglón es `fijo` y no hay lógica de selección.
 */
enum FunnelCourseKind: string
{
    case Fijo = 'fijo';
    case Elegible = 'elegible';
}
