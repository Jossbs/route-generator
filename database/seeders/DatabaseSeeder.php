<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * El orden importa: las reglas referencian cursos y especializaciones por slug
     * y fallan ruidosamente si el catálogo no está sembrado. El asesor va primero
     * porque es quien firma lo que se cree mientras el módulo no esté insertado.
     */
    public function run(): void
    {
        $this->call([
            AdvisorSeeder::class,
            ProductionCatalogSeeder::class,
        ]);
    }
}
