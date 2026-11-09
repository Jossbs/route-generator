<?php

declare(strict_types=1);

namespace App\Services\Documents;

/** Textos autorizados para acompañar la ruta inicial, 8 de noviembre de 2026. */
final class InitialRouteScriptCopy
{
    public const VERSION = '2026-11-08';

    /** @return list<array<string, mixed>> */
    public static function slots(): array
    {
        $audio = [
            'saludo' => '*{nombre}, ¿qué tal?* Soy {asesor}, de Desarrollo Profesional SyS.',
            'conexion' => 'Tu ruta no es un catálogo: {conexion_audio}, para que cada curso te sirva {aplicacion}.',
            'ruta' => 'Imagínate en *{duracion_audio}* con *{constancias_audio}* ante la STPS en tu expediente. (pausa) Eso es lo que te pone por delante cuando llega un ascenso o una mejor oferta.',
            'inicio' => 'Y es apenas el arranque: tu membresía te deja {catalogo_audio} todo el año, por mucho menos de lo que pagarías curso por curso.',
            'pago' => 'Para que el pago no sea lo que te detenga, tenemos facilidades.',
            'cierre' => 'Si te gustó tu ruta y quieres empezar hoy mismo, escríbeme *“¡Me gustó!”* y te mando las facilidades de pago y todos los detalles de tu membresía.',
        ];

        $message = [
            'saludo' => '{nombre}, tu ruta ya está lista 🎯 {conexion_mensaje}.',
            'ruta' => 'En {duracion} puedes tener {constancias} ante la STPS, y tu membresía te abre {catalogo} todo el año, por mucho menos de lo que pagarías curso por curso 📈',
            'pago' => 'Y tenemos facilidades para que el pago no te detenga.',
            'cierre' => '👉 Si te gustó y quieres empezar hoy, respóndeme “¡Me gustó!” y te mando las facilidades y todos los detalles',
        ];

        $slots = [];

        foreach (['guion' => $audio, 'mensaje' => $message] as $prefix => $blocks) {
            foreach ($blocks as $key => $body) {
                $slots[] = [
                    'slug' => $prefix.'.'.$key,
                    'scope' => $prefix === 'guion' ? 'guion_audio' : 'mensaje_whatsapp',
                    'applies_when' => ['document_type' => 'ruta-enfocada'],
                    'body' => $body,
                    'claims' => [
                        'Texto proporcionado y autorizado por el usuario el 2026-11-08.',
                        'Duración estimada con las horas netas y el mismo ritmo de estudio del PDF; no es un plazo garantizado.',
                        'Las constancias corresponden al total de cursos de la ruta; el acceso anual usa el total del catálogo congelado.',
                    ],
                    'status' => 'aprobado',
                ];
            }
        }

        return $slots;
    }

    /** @return list<string> */
    public static function retiredSlugs(): array
    {
        $slugs = [];

        foreach (['guion', 'mensaje'] as $prefix) {
            foreach (['conexion.con-contexto', 'conexion.por-area', 'conexion.por-ocupacion', 'ruta.tres', 'ruta.dos', 'ruta.uno', 'ruta.ninguno'] as $suffix) {
                $slugs[] = $prefix.'.'.$suffix;
            }
        }

        return [...$slugs, 'mensaje.inicio'];
    }
}
