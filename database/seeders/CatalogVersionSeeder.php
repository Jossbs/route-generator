<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CatalogVersion;
use Illuminate\Database\Seeder;

class CatalogVersionSeeder extends Seeder
{
    public const V1_LABEL = 'v1-sitio-2026-09-08';

    public function run(): void
    {
        CatalogVersion::updateOrCreate(
            ['label' => self::V1_LABEL],
            [
                'source' => 'sitio_web',
                'published_at' => '2026-09-08 00:00:00',
                'checksum' => hash_file('sha256', database_path('seeders/data/catalog_v1.php')),
                'notes' => 'Transcripción de https://academy.dpsys.com.mx/cursos. 64 cursos, 12 especializaciones, 55 programas de contenido. v2 llegará con Catalogo_DPSyS_v1.xlsx y se reconciliará contra esta.',
            ],
        );
    }
}
