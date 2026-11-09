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
     * Los datos variables salen del snapshot: el audio y el mensaje deben
     * coincidir con las cifras del PDF, sin fijar diez constancias ni 64 cursos.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, string|null>
     */
    protected function tokens(array $payload, Lead $lead): array
    {
        $count = isset($payload['ruta']['totales']['cursos'])
            ? (int) $payload['ruta']['totales']['cursos'] : null;
        $catalog = isset($payload['catalogo']['cursos']) ? (int) $payload['catalogo']['cursos'] : null;
        $context = $this->clean($payload['lead']['contexto'] ?? null);
        $occupation = $this->clean($payload['lead']['ocupacion'] ?? null);
        $student = $lead->area === LeadArea::Estudiante
            || preg_match('/^estudiante\b/iu', $occupation ?? '') === 1;

        return [
            'nombre' => $this->clean($payload['lead']['nombre'] ?? null),
            'asesor' => $this->clean($payload['asesor']['nombre'] ?? null),
            'conexion_audio' => $context === null ? 'la armé pensando en tu perfil' : 'la armé con lo que me contaste',
            'conexion_mensaje' => $context === null ? 'La armé pensando en tu perfil' : 'La armé con lo que me contaste',
            'aplicacion' => $student ? 'en tu desarrollo profesional' : 'en tu trabajo',
            'duracion' => RouteDuration::for($payload),
            'duracion_audio' => RouteDuration::for($payload, spoken: true),
            'constancias' => $count === null ? null : ($count === 1 ? '1 constancia DC-3' : $count.' constancias DC-3'),
            'constancias_audio' => $count === null ? null : ($count === 1 ? 'una constancia DC-3' : RouteDuration::number($count).' constancias DC-3'),
            'catalogo' => $catalog === null ? null : $catalog.($catalog === 1 ? ' curso' : ' cursos'),
            'catalogo_audio' => $catalog === null ? null : ($catalog === 1 ? 'un curso abierto' : RouteDuration::number($catalog).' cursos abiertos'),
        ];
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
