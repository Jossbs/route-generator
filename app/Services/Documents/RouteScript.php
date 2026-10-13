<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\LeadArea;
use App\Models\Lead;

/**
 * Lo que comparten el guión del audio y el mensaje escrito que lo sustituye.
 *
 * Los dos acompañan al PDF de la ruta inicial y los dos se arman con la misma
 * ruta, así que dicen lo mismo del lead con las mismas palabras que capturó el
 * asesor. El texto es copy de catálogo (`copy_blocks`) y pasa por el mismo
 * escaneo de fraseo que el documento: aquí solo se elige la variante que
 * corresponde y se marca qué parte es dato y qué parte va resaltada.
 */
abstract class RouteScript
{
    public function __construct(protected readonly CopyResolver $copy) {}

    /**
     * Cuántos recomendados quedaron encima del embudo, como sufijo de variante.
     *
     * @param  array<string, string|null>  $tokens
     */
    protected function recommendedVariant(array $tokens): string
    {
        return match (true) {
            (int) $tokens['recomendados'] >= 2 => 'dos',
            (int) $tokens['recomendados'] === 1 => 'uno',
            default => 'ninguno',
        };
    }

    /**
     * Lo que el texto puede decir del lead y de su ruta. Nada se teclea aquí:
     * todo sale de la captura, del embudo o de las cifras del catálogo.
     *
     * Los recomendados no se nombran —el PDF los nombra, y así el lead tiene un
     * motivo para abrirlo—; solo se cuentan. Decidido el 2026-09-23.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string|null>
     */
    protected function tokens(array $payload, Lead $lead): array
    {
        $filas = $payload['ruta']['cursos'] ?? [];
        $cursos = collect(is_array($filas) ? $filas : []);

        // La ruta principal es lo que vino del embudo. Un nivel que un paquete
        // «Completo» de la misma ruta ya contiene no se nombra: nunca se dice
        // «Excel Básico» si va Excel Completo.
        $principal = $cursos
            ->filter(fn (array $c): bool => ($c['rol'] ?? null) !== 'complementaria')
            ->reject(fn (array $c): bool => (bool) ($c['horas_absorbidas'] ?? false))
            ->pluck('nombre')
            ->values();

        return [
            'nombre' => $this->clean($payload['lead']['nombre'] ?? null),
            'asesor' => $this->clean($payload['asesor']['nombre'] ?? null),
            'area' => $this->area($lead),
            'ocupacion' => $this->midSentence($payload['lead']['ocupacion'] ?? null),
            'contexto' => $this->midSentence($payload['lead']['contexto'] ?? null),
            'ruta' => $this->clean($payload['ruta']['nombre'] ?? null)
                ?? $this->clean($payload['ruta']['especialidad_principal'] ?? null),
            'desde' => $principal->first(),
            'recomendados' => (string) $cursos->where('rol', 'complementaria')->count(),
            'cursos' => isset($payload['catalogo']['cursos']) ? (string) $payload['catalogo']['cursos'] : null,
        ];
    }

    /**
     * Una frase que el asesor capturó suelta, con mayúscula inicial, lista para
     * ir a media oración —después de dos puntos o de «como»—, donde el español
     * la pide en minúscula. Una sigla al inicio («TI», «GM») se queda como está.
     */
    private function midSentence(mixed $valor): ?string
    {
        $texto = rtrim(trim((string) $valor), ' .');

        if (preg_match('/^\p{Lu}\p{Ll}/u', $texto) === 1) {
            $texto = mb_strtolower(mb_substr($texto, 0, 1)).mb_substr($texto, 1);
        }

        return $texto === '' ? null : $texto;
    }

    /**
     * El área como se dice en voz alta: «Producción», no «Producción /
     * Manufactura»; y ninguna para quien no trabaja todavía, porque «tu trabajo
     * en Estudiante» no se puede decir. Sin área, cae en la variante que nombra
     * la ocupación.
     */
    private function area(Lead $lead): ?string
    {
        if ($lead->area === LeadArea::Estudiante) {
            return null;
        }

        if ($lead->area === null) {
            return $this->clean($lead->area_other);
        }

        $etiqueta = explode(' / ', $lead->area->label())[0];

        return trim((string) preg_replace('/\s*\(.*\)$/u', '', $etiqueta));
    }

    /**
     * La primera variante que se puede decir completa.
     *
     * @param  list<string>  $slugs
     * @param  array<string, string|null>  $tokens
     * @return list<array{texto: string, dato: string|null, enfasis: bool}>|null
     */
    protected function firstAvailable(array $slugs, array $tokens): ?array
    {
        foreach ($slugs as $slug) {
            $body = $this->copy->raw($slug);

            if ($body === null) {
                continue;
            }

            $segmentos = $this->segments($body, $tokens);

            if ($segmentos !== null) {
                return $segmentos;
            }
        }

        return null;
    }

    /**
     * Parte el cuerpo en tramos: texto, dato (`{clave}`) y énfasis (`*así*`).
     * Un dato puede ir dentro de un énfasis. Null si falta algún dato: una frase
     * con un hueco no se dice.
     *
     * @param  array<string, string|null>  $tokens
     * @return list<array{texto: string, dato: string|null, enfasis: bool}>|null
     */
    private function segments(string $body, array $tokens): ?array
    {
        $segmentos = [];

        foreach (explode('*', $body) as $i => $tramo) {
            $enfasis = $i % 2 === 1;

            foreach (preg_split('/(\{[a-z_0-9]+\})/', $tramo, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $pieza) {
                if (preg_match('/^\{([a-z_0-9]+)\}$/', $pieza, $m) === 1) {
                    $valor = $tokens[$m[1]] ?? null;

                    if (blank($valor)) {
                        return null;
                    }

                    $segmentos[] = ['texto' => $valor, 'dato' => $m[1], 'enfasis' => $enfasis];

                    continue;
                }

                $segmentos[] = ['texto' => $pieza, 'dato' => null, 'enfasis' => $enfasis];
            }
        }

        return $segmentos;
    }

    /** @param  list<array{texto: string}>  $segmentos */
    protected function plain(array $segmentos): string
    {
        return implode('', array_column($segmentos, 'texto'));
    }

    protected function words(string $texto): int
    {
        return count(preg_split('/\s+/u', trim($texto), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    private function clean(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
