<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\FunnelState;
use App\Models\Course;
use App\Models\Funnel;
use App\Models\FunnelRoute;
use App\Models\Specialization;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Una ruta curada de ejemplo, solo para desarrollo.
 *
 * **No debe llegar a producción.** Se llama a sí misma «EJEMPLO» en el nombre y
 * el seeder se niega a correr fuera de desarrollo. Nace disponible porque sin una
 * ruta con cursos el formulario del asesor no tiene de dónde armar el documento.
 *
 * Usa cursos reales del catálogo a propósito: un embudo de fábrica con cursos
 * inventados no le daría nada que evaluar al validador, que es justamente lo que
 * hace falta probar en desarrollo. El copy es descriptivo y neutro; las frases de
 * resultado siguen sin aprobarse y no se fabrican aquí.
 */
class FunnelSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('FunnelSeeder es material de desarrollo y no corre en producción.');
        }

        $author = User::query()->orderBy('id')->first();

        if ($author === null) {
            $this->command->warn('Sin usuarios en la base: el embudo de ejemplo necesita un autor. Corre `php artisan db:seed --class=AdvisorSeeder` primero.');

            return;
        }

        $funnel = Funnel::updateOrCreate(
            ['slug' => 'ejemplo-calidad'],
            [
                'internal_name' => 'EJEMPLO · Calidad para supervisión de piso',
                // Nombre público, ángulo y vigencia son columnas dormidas: no se
                // capturan y no se inventan aquí.
                'state' => FunnelState::Activo,
                'created_by' => $author->id,
                'internal_notes' => 'Ruta de ejemplo sembrada por FunnelSeeder.',
            ],
        );

        $route = FunnelRoute::updateOrCreate(
            ['funnel_id' => $funnel->id],
            [
                'name' => $funnel->internal_name,
                'position' => 0,
                'main_specialization_id' => Specialization::where('slug', 'calidad')->value('id'),
                'duration_months' => 12,
            ],
        );

        // Cursos reales, en un orden que respeta la secuencia pedagógica: las
        // siete herramientas antes que SPC, que es la cadena `recomendado` que el
        // validador vigila.
        $sequence = [
            'siete-herramientas-de-la-calidad' => 1,
            'poka-yoke' => 2,
            'sistema-andon' => 3,
            'spc' => 4,
            'amef' => 6,
            'solucion-de-problemas-8d' => 8,
        ];

        $courses = Course::whereIn('slug', array_keys($sequence))->pluck('id', 'slug');

        $missing = array_diff(array_keys($sequence), $courses->keys()->all());

        if ($missing !== []) {
            throw new RuntimeException('El embudo de ejemplo referencia cursos que no existen: '.implode(', ', $missing));
        }

        $position = 0;

        foreach ($sequence as $slug => $month) {
            $route->items()->updateOrCreate(
                ['course_id' => $courses[$slug]],
                ['position' => ++$position, 'suggested_month' => $month],
            );
        }
    }
}
