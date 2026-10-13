<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ContentUnit;
use App\Models\Course;
use App\Models\Specialization;
use App\Models\Tag;
use Illuminate\Database\Seeder;

/**
 * Siembra el catálogo v1 desde database/seeders/data/catalog_v1.php.
 *
 * Idempotente: cada fila se resuelve por slug, de modo que volver a correrlo
 * reconcilia en vez de duplicar.
 */
class CatalogSeeder extends Seeder
{
    /** Roles de presentación permitidos por omisión. */
    private const DEFAULT_ALLOWED_ROLES = [
        'ancla' => true,
        'complementaria' => true,
        'bloque_itinerario' => true,
        'inventario' => true,
    ];

    public function run(): void
    {
        /** @var array<string, mixed> $data */
        $data = require database_path('seeders/data/catalog_v1.php');

        $specializations = $this->seedSpecializations($data['specializations']);
        $sharedUnits = $this->seedSharedContentUnits($data['content_units'], $specializations);
        $courses = $this->seedCourses($data['specializations'], $specializations, $sharedUnits);

        $this->seedContainments($data['containments'], $courses);
        $this->seedPrerequisites($data['prerequisites'], $courses);
        $this->seedTags($data['tags'], $courses);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, Specialization>
     */
    private function seedSpecializations(array $rows): array
    {
        $specializations = [];

        foreach ($rows as $row) {
            $specializations[$row['slug']] = Specialization::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'description' => $row['description'] ?? null,
                    'display_order' => $row['display_order'],
                    'completeness_status' => $row['completeness_status'] ?? 'complete',
                    'missing_components' => $row['missing_components'] ?? null,
                    'allowed_roles' => $row['allowed_roles'] ?? self::DEFAULT_ALLOWED_ROLES,
                    'notes' => $row['notes'] ?? null,
                ],
            );
        }

        return $specializations;
    }

    /**
     * Las unidades que agrupan a más de un curso. Las 1:1 se crean junto al curso.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, Specialization>  $specializations
     * @return array<string, ContentUnit>
     */
    private function seedSharedContentUnits(array $rows, array $specializations): array
    {
        $units = [];

        foreach ($rows as $row) {
            $units[$row['slug']] = ContentUnit::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'name' => $row['name'],
                    'specialization_id' => $specializations[$row['specialization']]->id,
                ],
            );
        }

        return $units;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, Specialization>  $specializations
     * @param  array<string, ContentUnit>  $sharedUnits
     * @return array<string, Course>
     */
    private function seedCourses(array $rows, array $specializations, array $sharedUnits): array
    {
        $courses = [];

        foreach ($rows as $specializationRow) {
            $specialization = $specializations[$specializationRow['slug']];

            foreach ($specializationRow['courses'] as $courseRow) {
                $unit = isset($courseRow['unit'])
                    ? $sharedUnits[$courseRow['unit']]
                    : ContentUnit::updateOrCreate(
                        ['slug' => $courseRow['slug']],
                        ['name' => $courseRow['name'], 'specialization_id' => $specialization->id],
                    );

                $courses[$courseRow['slug']] = Course::updateOrCreate(
                    ['slug' => $courseRow['slug']],
                    [
                        'name' => $courseRow['name'],
                        'specialization_id' => $specialization->id,
                        'content_unit_id' => $unit->id,
                        'level' => $courseRow['level'] ?? 'unico',
                        'lessons_count' => $courseRow['lessons'],
                        'duration_hours' => $courseRow['hours'],
                        // Las 64 frases de resultado siguen pendientes de aprobación
                        // por Contenido: se dejan en null antes que inventarlas.
                        'outcome_phrase' => null,
                        'outcome_phrase_status' => 'borrador',
                        'delivery_mode' => 'autogestivo',
                        'credential_type' => 'dc3_stps',
                        'is_active' => true,
                    ],
                );
            }
        }

        return $courses;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, Course>  $courses
     */
    private function seedContainments(array $rows, array $courses): void
    {
        foreach ($rows as $row) {
            $parent = $courses[$row['parent']];

            foreach ($row['children'] as $childSlug) {
                $parent->contains()->syncWithoutDetaching([
                    $courses[$childSlug]->id => [
                        'containment_type' => $row['type'],
                        'verified_at' => '2026-09-08 00:00:00',
                        'notes' => $row['notes'],
                    ],
                ]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, Course>  $courses
     */
    private function seedPrerequisites(array $rows, array $courses): void
    {
        foreach ($rows as $row) {
            $courses[$row['course']]->prerequisites()->syncWithoutDetaching([
                $courses[$row['requires']]->id => ['strength' => $row['strength']],
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, Course>  $courses
     */
    private function seedTags(array $rows, array $courses): void
    {
        foreach ($rows as $row) {
            $tag = Tag::updateOrCreate(
                ['type' => $row['type'], 'slug' => $row['slug']],
                ['name' => $row['name']],
            );

            $tag->courses()->syncWithoutDetaching(
                array_map(fn (string $slug): int => $courses[$slug]->id, $row['courses']),
            );
        }
    }
}
