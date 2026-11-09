<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Catálogo inicial de producción, sin crear asesores ni embudos de ejemplo. */
class ProductionCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CatalogVersionSeeder::class,
            CatalogSeeder::class,
            CatalogAnalysisSeeder::class,
            DocumentTypeSeeder::class,
            CopySeeder::class,
            ArchetypeSeeder::class,
            CatalogRuleSeeder::class,
        ]);
    }
}
