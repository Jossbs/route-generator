<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Las dos capas de un documento. La ruta enfocada solo tiene itinerario;
 * la anual lleva itinerario recomendado + inventario de la membresía.
 */
enum RouteLayer: string
{
    case Itinerario = 'itinerario';
    case Inventario = 'inventario';
}
