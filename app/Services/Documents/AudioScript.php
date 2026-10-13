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
 * El 2026-09-23 pasó de 90–110 s a 45–60 s. El PDF ya hace el trabajo de
 * demostrar que la ruta es suya; el audio es complemento y su trabajo es
 * conectar: que el lead sienta que alguien escuchó lo que contó, que sepa que
 * esto es apenas el inicio —con la membresía llega la ruta de los 12 meses— y
 * que conteste para platicar certificaciones y facilidades de pago. Por eso ya
 * no nombra los cursos recomendados: los cuenta, y el PDF los nombra.
 *
 * Se guarda en el snapshot como todo lo demás: si mañana Contenido reescribe un
 * bloque, el guión de un audio que ya se grabó sigue siendo el que se leyó.
 */
final class AudioScript extends RouteScript
{
    /** A qué ritmo se lee. Es la cifra que ya usaba el módulo anterior. */
    public const PALABRAS_POR_MINUTO = 170;

    /** Lo que dura un audio que complementa al PDF sin competir con él. */
    public const SEGUNDOS_MIN = 45;

    public const SEGUNDOS_MAX = 60;

    /** Los bloques, en el orden en que se leen, con su nombre para el asesor. */
    private const BLOQUES = [
        'saludo' => 'Saludo',
        'conexion' => 'Lo que te contó',
        'ruta' => 'Su ruta',
        'inicio' => 'Apenas el inicio',
        'cierre' => 'Cierre',
    ];

    /**
     * @param  array<string, mixed>  $payload  El snapshot de la ruta, ya armado.
     * @return array<string, mixed>|null Null si el catálogo no tiene ni un bloque escrito.
     */
    public function for(array $payload, Lead $lead): ?array
    {
        $tokens = $this->tokens($payload, $lead);
        $recomendados = $this->recommendedVariant($tokens);

        $variantes = [
            'saludo' => ['guion.saludo'],
            'conexion' => ['guion.conexion.con-contexto', 'guion.conexion.por-area', 'guion.conexion.por-ocupacion'],
            'ruta' => ["guion.ruta.{$recomendados}"],
            'inicio' => ['guion.inicio'],
            'cierre' => ['guion.cierre'],
        ];

        $bloques = [];

        foreach (self::BLOQUES as $clave => $titulo) {
            $segmentos = $this->firstAvailable($variantes[$clave], $tokens);

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
