<?php

declare(strict_types=1);

use App\Enums\RouteLayer;
use App\Models\CatalogVersion;
use App\Models\Course;
use App\Services\RouteComposition\Support\CompositionCatalog;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PublishedCatalogSeeder;

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('cubre cada curso publicado por SYS con su especialidad, horas y lecciones', function () {
    $publication = json_decode(file_get_contents(database_path('seeders/data/catalog_publication_2026_11_09.json')), true);
    $courses = Course::with('specialization')->get()->keyBy('slug');

    expect($publication['courses'])->toHaveCount(64)
        ->and($courses->keys()->sort()->values()->all())
        ->toBe(collect($publication['courses'])->pluck('slug')->sort()->values()->all());

    foreach ($publication['courses'] as $row) {
        $course = $courses[$row['slug']];
        expect($course->name)->toBe($row['name'])
            ->and($course->specialization->name)->toBe($row['specialization'])
            ->and($course->duration_hours)->toBe($row['hours'])
            ->and($course->lessons_count)->toBe($row['lessons'])
            ->and($course->is_active)->toBeTrue()
            ->and($course->allowsLayer(RouteLayer::Itinerario))->toBeTrue();
    }

    $catalog = new CompositionCatalog;
    expect($catalog->recommendable())->toHaveCount(64)
        ->and($catalog->included())->toHaveCount(64)
        ->and(CatalogVersion::current()->label)->toBe(PublishedCatalogSeeder::LABEL);
});

it('migra las horas antiguas y reactiva PPAP sin sobrescribir contenido aprobado', function () {
    $ppap = Course::where('slug', 'ppap')->sole();
    $ppap->update(['is_active' => false, 'outcome_phrase' => 'Texto revisado por Contenido', 'outcome_phrase_status' => 'aprobado']);
    Course::where('slug', 'amef')->update(['duration_hours' => 12]);
    Course::where('slug', 'mente-millonaria')->update(['duration_hours' => null, 'lessons_count' => null, 'allowed_layers' => ['inventario']]);

    $migration = require database_path('migrations/2026_11_09_100000_reconcile_published_course_catalog.php');
    $migration->up();
    $migration->up();

    expect(Course::count())->toBe(64)
        ->and($ppap->fresh()->is_active)->toBeTrue()
        ->and($ppap->fresh()->outcome_phrase)->toBe('Texto revisado por Contenido')
        ->and($ppap->fresh()->outcome_phrase_status)->toBe('aprobado')
        ->and(Course::where('slug', 'amef')->sole()->duration_hours)->toBe(5)
        ->and(Course::where('slug', 'mente-millonaria')->sole()->duration_hours)->toBe(1)
        ->and(Course::recommendable()->count())->toBe(64)
        ->and(CatalogVersion::where('label', PublishedCatalogSeeder::LABEL)->count())->toBe(1);
});

it('reincorpora PPAP si falta en una instalación existente', function () {
    Course::where('slug', 'ppap')->delete();

    $migration = require database_path('migrations/2026_11_09_100000_reconcile_published_course_catalog.php');
    $migration->up();

    $ppap = Course::where('slug', 'ppap')->sole();
    expect(Course::count())->toBe(64)
        ->and($ppap->specialization->slug)->toBe('coretools')
        ->and($ppap->duration_hours)->toBe(10)
        ->and($ppap->lessons_count)->toBe(6)
        ->and($ppap->complexity_level)->not->toBeNull()
        ->and($ppap->tags()->where('slug', 'automotriz')->exists())->toBeTrue()
        ->and(Course::recommendable()->whereKey($ppap->id)->exists())->toBeTrue();
});
