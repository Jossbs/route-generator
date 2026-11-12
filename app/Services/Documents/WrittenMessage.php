<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Lead;

/**
 * El mensaje escrito que sustituye al audio, decidido el 2026-09-23.
 *
 * Cuando el asesor no puede grabar igual tiene que contestar, y el PDF solo en
 * el chat se siente frío. Este mensaje va pegado junto al archivo. No es el
 * guión transcrito —lo que se oye bien se lee largo—: dice lo mismo en frases
 * cortas, con negritas y emojis de WhatsApp, y deja el detalle al PDF para no
 * restarle peso.
 *
 * `*así*` en el copy sale como negrita de WhatsApp en `texto`, que es lo que se
 * pega en el chat.
 */
final class WrittenMessage extends RouteScript
{
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
            $segmentos = $this->firstAvailable(['mensaje.'.$clave], $tokens);

            if ($segmentos !== null) {
                $bloques[] = ['clave' => $clave, 'titulo' => $titulo, 'segmentos' => $segmentos];
            }
        }

        if ($bloques === []) {
            return null;
        }

        $texto = implode("\n\n", array_map(fn (array $b): string => $this->whatsApp($b['segmentos']), $bloques));

        return [
            'version' => InitialRouteScriptCopy::VERSION,
            'bloques' => $bloques,
            'palabras' => $this->words($texto),
            'texto' => $texto,
        ];
    }

    /**
     * El bloque como se pega en WhatsApp: cada tramo seguido de énfasis entre
     * asteriscos. WhatsApp no pone negrita si el asterisco toca un espacio, así
     * que los espacios de las orillas quedan afuera.
     *
     * @param  list<array{texto: string, dato: string|null, enfasis: bool}>  $segmentos
     */
    private function whatsApp(array $segmentos): string
    {
        $texto = '';
        $tramo = '';

        foreach ([...$segmentos, ['texto' => '', 'dato' => null, 'enfasis' => false]] as $segmento) {
            if ($segmento['enfasis']) {
                $tramo .= $segmento['texto'];

                continue;
            }

            if ($tramo !== '') {
                if (preg_match('/^(\s*)(.*?)(\s*)$/su', $tramo, $m) === 1) {
                    $texto .= $m[1].'*'.$m[2].'*'.$m[3];
                } else {
                    $texto .= $tramo;
                }
                $tramo = '';
            }

            $texto .= $segmento['texto'];
        }

        return $texto;
    }
}
