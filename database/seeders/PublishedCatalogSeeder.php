<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CatalogVersion;
use App\Models\ContentUnit;
use App\Models\Course;
use App\Models\Specialization;
use App\Models\Tag;
use App\Services\Catalog\ComplexityRubric;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/** Reconcilia la publicación auditada sin cambiar los documentos ya emitidos. */
class PublishedCatalogSeeder extends Seeder
{
    public const LABEL = 'sitio-2026-11-09';

    public function __construct(private readonly ComplexityRubric $rubric) {}

    public function run(): void
    {
        $path = database_path('seeders/data/catalog_publication_2026_11_09.json');
        $publication = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        $catalog = require database_path('seeders/data/catalog_v1.php');
        $analysis = require database_path('seeders/data/course_analysis_v1.php');
        $descriptions = require database_path('seeders/data/course_descriptions_v1.php');
        $definitions = [];
        foreach ($catalog['specializations'] as $specialization) {
            foreach ($specialization['courses'] as $definition) {
                $definitions[$definition['slug']] = $definition;
            }
        }

        DB::transaction(function () use ($path, $publication, $catalog, $analysis, $descriptions, $definitions): void {
            foreach ($publication['courses'] as $row) {
                $slug = $row['slug'];
                $course = Course::firstOrNew(['slug' => $slug]);
                $wasMissing = ! $course->exists;
                $specialization = Specialization::where('name', $row['specialization'])->sole();
                $definition = $definitions[$slug];

                if (! $course->exists) {
                    $unit = ContentUnit::firstOrCreate(
                        ['slug' => $definition['unit'] ?? $slug],
                        ['name' => $row['name'], 'specialization_id' => $specialization->id],
                    );
                    $course->fill([
                        'content_unit_id' => $unit->id,
                        'level' => $definition['level'] ?? 'unico',
                        'short_description' => $descriptions[$slug],
                        'outcome_phrase' => $descriptions[$slug],
                        'outcome_phrase_status' => 'borrador',
                        'allowed_layers' => $analysis[$slug]['layers'] ?? null,
                        'needs_overlap_review' => $analysis[$slug]['overlap_review'] ?? false,
                        'needs_duration_review' => $analysis[$slug]['duration_review'] ?? false,
                        'analysis_note' => $analysis[$slug]['note'] ?? null,
                    ]);
                }

                $score = $this->rubric->score($row['hours'], $row['lessons'], $analysis[$slug]['technical'], $analysis[$slug]['scope']);
                $course->fill([
                    'name' => $row['name'],
                    'specialization_id' => $specialization->id,
                    'duration_hours' => $row['hours'],
                    'lessons_count' => $row['lessons'],
                    'complexity_index' => $score?->index,
                    'complexity_level' => $score?->level,
                    'complexity_criteria' => $score?->criteria(),
                    'is_active' => true,
                ]);

                // Estos tres ya publican 1 lección / 1 hora. Se retira únicamente
                // la exclusión que se debía a la falta de duración en septiembre.
                if (in_array($slug, ['mente-millonaria', 'liderazgo-efectivo', 'estrategias-de-negociacion'], true)) {
                    if ($course->allowed_layers === ['inventario']) {
                        $course->allowed_layers = null;
                    }
                    $course->analysis_note = 'El sitio publica 1 lección y 1 hora desde la revisión del 2026-11-09. Se conservan las reglas de traslape.';
                }

                $course->save();

                if ($wasMissing) {
                    foreach ($catalog['tags'] as $tagDefinition) {
                        if (in_array($slug, $tagDefinition['courses'], true)) {
                            $tag = Tag::firstOrCreate(
                                ['type' => $tagDefinition['type'], 'slug' => $tagDefinition['slug']],
                                ['name' => $tagDefinition['name']],
                            );
                            $course->tags()->syncWithoutDetaching([$tag->id]);
                        }
                    }
                }
            }

            CatalogVersion::updateOrCreate(['label' => self::LABEL], [
                'source' => 'sitio_web',
                'published_at' => '2026-11-09 00:00:00',
                'checksum' => hash_file('sha256', $path),
                'notes' => 'Cotejo de los 64 cursos y 12 especializaciones en https://academy.dpsys.com.mx/cursos. Actualiza 41 duraciones, incluidas las tres antes desconocidas. PPAP: 6 lecciones, 10 horas, Core Tools. Conserva IDs y snapshots anteriores.',
            ]);
        });
    }
}
