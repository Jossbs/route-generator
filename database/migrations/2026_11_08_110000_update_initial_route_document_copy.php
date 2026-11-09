<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Correcciones editoriales solicitadas para la ruta inicial. */
    private const TEXTOS = [
        'objetivo.ruta-enfocada' => [
            'antes' => 'Para tu puesto de {ocupacion}: empieza por {ancla} y sigue con lo que más la refuerza.',
            'ahora' => 'Empieza por {ancla}, la ruta por la que preguntaste, y avanza con cursos de refuerzo elegidos según lo que nos compartiste. Cada curso cierra con su constancia oficial.',
        ],
        'membresia.alcance' => [
            'antes' => 'Tu ruta es el inicio. Con la membresía se abren todas las especializaciones y te preparamos tu ruta de 12 meses.',
            'ahora' => 'Esta ruta es solo el inicio. Tu membresía te da acceso al resto del catálogo: al terminarla, puedes aprovechar el tiempo que te quede de tus 12 meses de acceso para seguir aprendiendo y acreditando más cursos.',
        ],
        'siguiente-paso.ruta-enfocada' => [
            'antes' => '¿Lo platicamos? Respóndeme y vemos juntos las certificaciones y las facilidades de pago.',
            'ahora' => 'Da el siguiente paso hacia tu objetivo. Responde a nuestra conversación y te cuento cómo obtener tus constancias y diplomas, qué certificaciones acompañan tu ruta y qué facilidades de pago tienes para empezar. Resolvemos tus dudas y elegimos juntos la opción que mejor se adapte a ti.',
        ],
        'cta.boton-whatsapp' => [
            'antes' => 'Escríbeme por WhatsApp',
            'ahora' => 'Conocer certificaciones y pagos',
        ],
        'cta.mensaje-whatsapp' => [
            'antes' => 'Hola, soy {nombre}. Ya vi mi ruta formativa y quiero que la revisemos juntos.',
            'ahora' => 'Hola, soy {nombre}. Ya vi mi ruta principal personalizada. Quiero conocer las certificaciones y las facilidades de pago para empezar.',
        ],
    ];

    public function up(): void
    {
        foreach (self::TEXTOS as $slug => $textos) {
            DB::table('copy_blocks')->where('slug', $slug)->update([
                'body' => $textos['ahora'],
                'updated_at' => now(),
            ]);
        }

        foreach (['guion', 'mensaje'] as $prefijo) {
            $original = DB::table('copy_blocks')->where('slug', $prefijo.'.ruta.dos')->first();

            if ($original === null) {
                continue;
            }

            $nuevo = (array) $original;
            unset($nuevo['id']);
            $nuevo['slug'] = $prefijo.'.ruta.tres';
            $nuevo['body'] = str_replace('dos cursos', 'tres cursos', $original->body);
            $nuevo['created_at'] = $nuevo['updated_at'] = now();
            DB::table('copy_blocks')->insertOrIgnore($nuevo);
        }
    }

    public function down(): void
    {
        foreach (self::TEXTOS as $slug => $textos) {
            DB::table('copy_blocks')->where('slug', $slug)->where('body', $textos['ahora'])->update([
                'body' => $textos['antes'],
                'updated_at' => now(),
            ]);
        }
    }
};
