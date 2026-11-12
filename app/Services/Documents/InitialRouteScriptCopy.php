<?php

declare(strict_types=1);

namespace App\Services\Documents;

/** Textos autorizados para acompañar la ruta inicial, 11 de noviembre de 2026. */
final class InitialRouteScriptCopy
{
    public const VERSION = '2026-11-11';

    /** @return list<array<string, mixed>> */
    public static function slots(): array
    {
        $audio = [
            'saludo' => '¡Hola, *{nombre}*! ¿Cómo estás? Te saluda {asesor}, asesora de Desarrollo Profesional SyS.',
            'conexion' => 'Te comento que ya tengo lista tu ruta de capacitación. La preparé tomando en cuenta tu perfil y seleccionando los cursos que considero más adecuados para tu desarrollo profesional.',
            'ruta' => 'Con esta ruta, en aproximadamente *{duracion_audio}* podrías obtener *{constancias_audio}* ante la STPS.',
            'inicio' => 'Además, algo importante es que tu membresía no se limita a estos cursos. Tendrás acceso a {catalogo_audio} con sus certificaciones durante 12 meses, sin pagar cada uno por separado.',
            'pago' => 'También contamos con diferentes facilidades de pago.',
            'cierre' => 'Me gustaría conocer tu opinión, *{nombre}*. ¿Qué te pareció la ruta? ¿Te comparto las opciones de pago o prefieres que te explique más sobre las certificaciones?',
        ];

        $message = [
            'saludo' => '¡{nombre}, tu *ruta de capacitación personalizada* ya está lista! 🎯',
            'conexion' => 'La preparé considerando tu perfil y las certificaciones que pueden ayudarte a alcanzar tus objetivos profesionales.',
            'ruta' => '🎓 En *{duracion} podrías obtener {constancias} ante la STPS*.',
            'inicio' => '📚 Además, tu membresía incluye *{catalogo} + certificaciones durante 12 meses*, sin pagar cada curso por separado.',
            'pago' => '💳 También contamos con facilidades de pago para que puedas comenzar tu capacitación.',
            'cierre' => '👉 *¿Qué te pareció la ruta que preparé para ti?* 😊 ¿Te gustaría conocer las facilidades de pago o más detalles sobre las certificaciones?',
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
                        'Texto proporcionado y autorizado por el usuario el 2026-11-11.',
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

        return $slugs;
    }
}
