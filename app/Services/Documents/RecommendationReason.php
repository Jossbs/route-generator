<?php

declare(strict_types=1);

namespace App\Services\Documents;

/** Explica el refuerzo usando únicamente el contenido congelado del curso. */
final class RecommendationReason
{
    private const VERBOS = [
        'Elimina' => 'eliminar', 'Identifica' => 'identificar', 'Mapea' => 'mapear',
        'Balancea' => 'balancear', 'Maximiza' => 'maximizar', 'Estandariza' => 'estandarizar',
        'Controla' => 'controlar', 'Adopta' => 'adoptar', 'Apoya' => 'apoyar',
        'Lidera' => 'liderar', 'Dirige' => 'dirigir', 'Lleva' => 'llevar',
        'Construye' => 'construir', 'Transmite' => 'transmitir', 'Coordina' => 'coordinar',
        'Gestiona' => 'gestionar', 'Influye' => 'influir', 'Consigue' => 'conseguir',
        'Muestra' => 'mostrar', 'Llega' => 'llegar', 'Cambia' => 'cambiar',
        'Cierra' => 'cerrar', 'Analiza' => 'analizar', 'Diseña' => 'diseñar',
        'Organiza' => 'organizar', 'Diagnostica' => 'diagnosticar', 'Implementa' => 'implementar',
        'Mide' => 'medir', 'Optimiza' => 'optimizar', 'Despliega' => 'desplegar',
        'Resuelve' => 'resolver', 'Documenta' => 'documentar', 'Planea' => 'planear',
        'Consulta' => 'consultar', 'elimina' => 'eliminar', 'detecta' => 'detectar',
        'reduce' => 'reducir', 'domina' => 'dominar', 'motiva' => 'motivar',
        'desarrolla' => 'desarrollar', 'convence' => 'convencer', 'previene' => 'prevenir',
        'gana' => 'ganar', 'resuelve' => 'resolver', 'estandariza' => 'estandarizar',
        'asegura' => 'asegurar', 'ejecuta' => 'ejecutar', 'administra' => 'administrar',
    ];

    /** @param array<string, mixed> $course */
    public function for(array $course): string
    {
        $benefit = rtrim(trim((string) ($course['frase_resultado'] ?? '')), ' .');
        if ($benefit === '') {
            return 'Amplía tu formación en '.($course['especialidad'] ?? 'esta área').' como complemento de tu ruta principal.';
        }

        $first = explode(' ', $benefit, 2)[0];

        if (! isset(self::VERBOS[$first])) {
            return 'Su enfoque complementa tu ruta: '.$benefit.'.';
        }

        $verbs = implode('|', array_map(preg_quote(...), array_keys(self::VERBOS)));
        $benefit = preg_replace_callback(
            '/(^| y )('.$verbs.')\b/u',
            fn (array $match): string => $match[1].self::VERBOS[$match[2]],
            $benefit,
        ) ?? $benefit;

        return 'Te lo recomiendo para '.$benefit.'.';
    }
}
