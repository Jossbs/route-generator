<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Course;
use App\Services\Catalog\ComplexityRubric;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * La capa de análisis sobre el catálogo ya sembrado: descripciones literales del
 * sitio, rúbrica de complejidad y banderas de auditoría.
 *
 * Corre después de CatalogSeeder y falla ruidosamente si un slug no existe: un
 * curso sin criterios de juicio quedaría sin nivel y el generador lo ordenaría al
 * final de cualquier itinerario sin que nadie lo note.
 */
class CatalogAnalysisSeeder extends Seeder
{
    public function __construct(private readonly ComplexityRubric $rubric) {}

    public function run(): void
    {
        /** @var array<string, string> $descriptions */
        $descriptions = require database_path('seeders/data/course_descriptions_v1.php');

        /** @var array<string, array<string, mixed>> $analysis */
        $analysis = require database_path('seeders/data/course_analysis_v1.php');

        $courses = Course::query()->get()->keyBy('slug');

        $slugs = array_map(strval(...), array_values($courses->keys()->all()));

        $this->assertCoverage($slugs, array_keys($descriptions), 'descripción corta');
        $this->assertCoverage($slugs, array_keys($analysis), 'criterios de análisis');

        foreach ($courses as $slug => $course) {
            $row = $analysis[$slug];

            $score = $this->rubric->score(
                durationHours: $course->duration_hours,
                lessonsCount: $course->lessons_count,
                technicalPrerequisite: (int) $row['technical'],
                applicationScope: (int) $row['scope'],
            );

            $course->update([
                'short_description' => $descriptions[$slug],
                // docs/ARQUITECTURA.md §6: las frases de resultado se siembran en borrador con
                // la descripción que ya publica el curso. Contenido las reescribe y
                // recién entonces pasan a `aprobado`; hasta ese día, el PDF imprime
                // texto real de DP SyS en vez de una promesa inventada.
                'outcome_phrase' => $descriptions[$slug],
                'outcome_phrase_status' => 'borrador',
                'complexity_index' => $score?->index,
                'complexity_level' => $score?->level,
                'complexity_criteria' => $score?->criteria(),
                'allowed_layers' => $row['layers'] ?? null,
                'needs_overlap_review' => (bool) ($row['overlap_review'] ?? false),
                'needs_duration_review' => (bool) ($row['duration_review'] ?? false),
                'analysis_note' => $row['note'] ?? null,
            ]);
        }
    }

    /**
     * @param  list<string>  $expected
     * @param  list<string>  $provided
     */
    private function assertCoverage(array $expected, array $provided, string $what): void
    {
        $missing = array_diff($expected, $provided);

        if ($missing !== []) {
            throw new RuntimeException(
                "Faltan datos de {$what} para: ".implode(', ', $missing),
            );
        }

        $unknown = array_diff($provided, $expected);

        if ($unknown !== []) {
            throw new RuntimeException(
                "Hay datos de {$what} para cursos que no existen en el catálogo: ".implode(', ', $unknown),
            );
        }
    }
}
