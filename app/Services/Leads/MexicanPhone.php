<?php

declare(strict_types=1);

namespace App\Services\Leads;

/**
 * El WhatsApp del lead, escrito siempre igual: `+52` y los diez dígitos.
 *
 * El teléfono es la llave con la que se reconoce al lead que vuelve por su ruta
 * final. Guardado como vino —«442 118 5502», «+52 4421185502», «4421185502»— el
 * mismo número eran tres leads distintos y la conversión gancho → membresía se
 * perdía. Los leads son de México, así que la lada se pone aquí y la pantalla
 * solo pide los diez dígitos.
 */
final class MexicanPhone
{
    public const LADA = '+52';

    public const DIGITOS = 10;

    /**
     * El número canónico, o null si no son diez dígitos.
     *
     * Acepta que venga con la lada —por URL desde el CRM, o pegado del chat— y
     * con el `1` que WhatsApp todavía antepone a los celulares viejos (`521…`).
     */
    public static function normalize(?string $numero): ?string
    {
        $nacional = self::national($numero);

        return $nacional === null ? null : self::LADA.$nacional;
    }

    /** Los diez dígitos sin lada, que es lo que la pantalla muestra y pide. */
    public static function national(?string $numero): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $numero) ?? '';

        $digitos = match (true) {
            strlen($digitos) === 13 && str_starts_with($digitos, '521') => substr($digitos, 3),
            strlen($digitos) === 12 && str_starts_with($digitos, '52') => substr($digitos, 2),
            default => $digitos,
        };

        return strlen($digitos) === self::DIGITOS ? $digitos : null;
    }

    /** «+52 442 118 5502»: como se lee en la bandeja y en la ficha. */
    public static function display(?string $numero): ?string
    {
        $nacional = self::national($numero);

        if ($nacional === null) {
            return blank($numero) ? null : $numero;
        }

        return self::LADA.' '.substr($nacional, 0, 3).' '.substr($nacional, 3, 3).' '.substr($nacional, 6);
    }
}
