<?php

declare(strict_types=1);

use App\Models\Course;
use Database\Seeders\PublishedCatalogSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // En una instalación nueva el catálogo se carga con el seeder de producción.
        if (Course::exists()) {
            app(PublishedCatalogSeeder::class)->run();
        }
    }

    public function down(): void
    {
        // No se restauran horas desactualizadas ni se alteran snapshots emitidos.
    }
};
