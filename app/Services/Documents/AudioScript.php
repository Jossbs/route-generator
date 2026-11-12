<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Lead;

/**
 * El guión del audio que acompaña a la ruta inicial.
 *
 * Antes era un módulo aparte donde el asesor volvía a teclear al mismo lead.
 * Desde el 2026-09-22 se arma con la ruta, en el mismo momento, y dice lo mismo
 * que el PDF que el lead va a abrir.
 *
 * El texto autorizado el 2026-11-11 conecta la ruta con la duración estimada,
 * las constancias que puede obtener y los 12 meses de membresía. Cierra
 * preguntando su opinión y ofreciendo opciones de pago o detalles de certificación.
 *
 * Se guarda en el snapshot como todo lo demás: si mañana Contenido reescribe un
 * bloque, el guión de un audio que ya se grabó sigue siendo el que se leyó.
 */
final class AudioScript extends RouteScript
{
    /** A qué ritmo se lee. Es la cifra que ya usaba el módulo anterior. */
    public const PALABRAS_POR_MINUTO = 170;

    /** Lo que dura un audio que complementa al PDF sin competir con él. */
    public const SEGUNDOS_MIN = 40;

    public const SEGUNDOS_MAX = 60;

    /** Los bloques, en el orden en que se leen, con su nombre para el asesor. */
    private const BLOQUES = [
        'saludo' => 'Saludo',
        'conexion' => 'Su perfil',
        'ruta' => 'Su ruta',
        'inicio' => 'Su membresía',
        'pago' => 'Facilidades de pago',
        'cierre' => 'Cierre',
    ];

    /**
     * @param  array<string, mixed>  $payload  El snapshot de la ruta, ya armado.
     * @return array<string, mixed>|null Null si el catálogo no tiene ni un bloque escrito.
     */
    public function for(array $payload, Lead $lead): ?array
    {
        $tokens = $this->tokens($payload, $lead);

        $bloques = [];

        foreach (self::BLOQUES as $clave => $titulo) {
            $segmentos = $this->firstAvailable(['guion.'.$clave], $tokens);

            if ($segmentos === null) {
                continue;
            }

            $palabras = $this->words($this->plain($segmentos));

            $bloques[] = [
                'clave' => $clave,
                'titulo' => $titulo,
                'segmentos' => $segmentos,
                'palabras' => $palabras,
                'segundos' => $this->seconds($palabras),
            ];
        }

        if ($bloques === []) {
            return null;
        }

        $palabras = array_sum(array_column($bloques, 'palabras'));

        return [
            'version' => InitialRouteScriptCopy::VERSION,
            'bloques' => $bloques,
            'palabras' => $palabras,
            'segundos' => $this->seconds($palabras),
            'menciones_nombre' => count(array_filter(
                array_merge(...array_column($bloques, 'segmentos')),
                fn (array $segmento): bool => $segmento['dato'] === 'nombre',
            )),
            'texto' => implode("\n\n", array_map(fn (array $b): string => $this->plain($b['segmentos']), $bloques)),
        ];
    }

    private function seconds(int $palabras): int
    {
        return (int) round($palabras / self::PALABRAS_POR_MINUTO * 60);
    }
}
