<?php

declare(strict_types=1);

namespace App\Services\RouteGeneration;

/**
 * El sello del snapshot, calculado sobre una forma canónica.
 *
 * PostgreSQL guarda `jsonb` normalizado y no conserva el orden en que se
 * escribieron las claves. Hashear el JSON tal como sale de `json_encode` daría un
 * sello que deja de cuadrar en cuanto el payload se relee de la base, aunque nadie
 * lo haya tocado: una alarma que suena sola es peor que no tenerla.
 *
 * Ordenar las claves antes de hashear vuelve el sello independiente de por dónde
 * pasó el payload. Lo que detecta es lo único que importa: que el contenido cambió
 * después de emitirse.
 */
final class PayloadHash
{
    /** @param array<array-key, mixed> $payload */
    public static function of(array $payload): string
    {
        return hash('sha256', (string) json_encode(
            self::canonicalize($payload),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private static function canonicalize(array $value): array
    {
        // Las listas conservan su orden: en una ruta, el orden de los cursos es
        // parte del contenido, no una casualidad de serialización.
        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::canonicalize($item);
            }
        }

        return $value;
    }
}
