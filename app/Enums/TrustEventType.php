<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lo que el centro de confianza avisa de vuelta.
 *
 * Son cuatro y bastan para contestar lo que el asesor quiere saber: si el lead lo
 * abrió, hasta dónde leyó, qué duda le importó y si terminó escribiendo.
 */
enum TrustEventType: string
{
    case Abierta = 'abierta';
    case SeccionVista = 'seccion_vista';
    case PreguntaAbierta = 'pregunta_abierta';
    case Clic = 'clic';
}
