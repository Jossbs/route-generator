<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\DocumentVisit;
use App\Models\LearningRoute;

final class DocumentReading
{
    public const SECTIONS = [
        'portada' => 'Portada',
        'ruta' => 'Tu ruta y cursos recomendados',
        'confianza' => 'Quiénes somos y respaldos',
        'siguiente-paso' => 'Membresía y siguiente paso',
        'mapa' => 'Plan de los doce meses',
        'membresia' => 'Catálogo incluido en la membresía',
        'empieza-hoy' => 'Cómo empezar',
    ];

    public const CLICKS = [
        'membresia' => 'Consultar membresía por WhatsApp',
        'conversacion' => 'Hablar con el asesor por WhatsApp',
        'respaldo' => 'Consultar un respaldo',
    ];

    /** @return array<string, mixed> */
    public function summary(LearningRoute $route): array
    {
        $visits = DocumentVisit::query()->where('route_id', $route->id)
            ->where('revision', $route->revisions)->orderByDesc('updated_at')->get();
        $sections = [];
        foreach (self::SECTIONS as $id => $label) {
            $ms = $visits->sum(fn (DocumentVisit $visit): int => $visit->sections[$id] ?? 0);
            if ($ms > 0) {
                $sections[] = ['id' => $id, 'nombre' => $label, 'segundos' => (int) floor($ms / 1000)];
            }
        }
        $clicks = [];
        foreach (self::CLICKS as $id => $label) {
            $count = $visits->filter(fn (DocumentVisit $visit): bool => in_array($id, $visit->clicks, true))->count();
            if ($count > 0) {
                $clicks[] = ['nombre' => $label, 'visitas' => $count];
            }
        }
        $last = $visits->first();
        $progress = (int) ($visits->max('progress') ?? 0);
        $suggestion = match (true) {
            $visits->isEmpty() => 'Confirma que recibió el enlace y ofrécele revisar la ruta contigo.',
            $visits->contains(fn (DocumentVisit $visit): bool => in_array('membresia', $visit->clicks, true)) => 'Retoma su consulta sobre la membresía y aclara los pasos para inscribirse.',
            $visits->contains(fn (DocumentVisit $visit): bool => in_array('conversacion', $visit->clicks, true)) => 'Revisa el chat y responde las dudas que haya compartido.',
            $visits->contains(fn (DocumentVisit $visit): bool => isset($visit->sections['siguiente-paso'])) => 'Pregúntale qué duda tiene sobre la membresía o cómo comenzar.',
            $visits->contains(fn (DocumentVisit $visit): bool => isset($visit->sections['confianza'])) => 'Ofrece aclarar sus dudas sobre respaldos, constancias y modalidad.',
            $visits->contains(fn (DocumentVisit $visit): bool => isset($visit->sections['ruta'])) => 'Pregunta qué curso le interesa más y si la ruta responde a su objetivo.',
            default => 'Invítale a revisar los cursos recomendados y ofrécele acompañarlo.',
        };

        return [
            'visitas' => $visits->count(),
            'avance' => $progress,
            'seccion_mas_lejana' => self::SECTIONS[$visits->sortByDesc('progress')->first()->furthest_section ?? ''] ?? null,
            'segundos' => (int) floor($visits->sum('active_ms') / 1000),
            'ultima_actividad' => $last?->updated_at->toIso8601String(),
            'ultima_seccion' => self::SECTIONS[$last->last_section ?? ''] ?? null,
            'secciones' => $sections,
            'clics' => $clicks,
            'sugerencia' => $suggestion,
        ];
    }
}
