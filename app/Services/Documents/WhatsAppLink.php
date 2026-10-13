<?php

declare(strict_types=1);

namespace App\Services\Documents;

/**
 * El enlace de vuelta al asesor.
 *
 * El documento no termina: devuelve la conversación a quien puede atender
 * objeciones. Hasta ahora el cierre decía «respóndeme por WhatsApp» y el lead
 * tenía que salir del documento y buscar el chat; un `wa.me` lo resuelve en un
 * toque, que es donde se pierden las conversiones.
 *
 * Nunca imprime un enlace que no vaya a abrir nada: si el número no alcanza para
 * marcar, devuelve `null` y la plantilla no pinta el botón.
 */
final class WhatsAppLink
{
    /** Lada de México. El catálogo y los asesores son de aquí. */
    private const LADA_MX = '52';

    /** Un número nacional sin lada. Con lada, son doce. */
    private const DIGITOS_NACIONALES = 10;

    /**
     * El enlace, o null si el número no sirve para marcar.
     *
     * WhatsApp quiere el número en dígitos y con lada, sin `+`, espacios ni
     * guiones: el asesor lo captura como quiera y aquí se normaliza. Diez dígitos
     * es un número nacional escrito como se escribe en México, así que se le
     * antepone la lada; con más dígitos se asume que ya la trae.
     */
    public function for(?string $numero, ?string $mensaje = null): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $numero) ?? '';

        if (mb_strlen($digitos) < self::DIGITOS_NACIONALES) {
            return null;
        }

        if (mb_strlen($digitos) === self::DIGITOS_NACIONALES) {
            $digitos = self::LADA_MX.$digitos;
        }

        $url = 'https://wa.me/'.$digitos;

        return blank($mensaje) ? $url : $url.'?text='.rawurlencode($mensaje);
    }
}
