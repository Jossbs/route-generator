<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

/**
 * Los dos documentos de la v1. Un tercero (renovación, reactivación) entra como
 * INSERT, no como deploy: por eso `layers` y `composition_policy` son datos.
 */
class DocumentTypeSeeder extends Seeder
{
    public const ENFOCADA = 'ruta-enfocada';

    public const ANUAL = 'ruta-anual';

    public function run(): void
    {
        DocumentType::updateOrCreate(
            ['slug' => self::ENFOCADA],
            [
                'name' => 'Ruta enfocada',
                'delivery' => 'enlace_rastreado',
                'requires_membership' => false,
                'layers' => ['itinerario'],
                'composition_policy' => [
                    'ancla' => 'especializacion_completa',
                    'complementarias' => 2,
                    'cursos_por_complementaria' => ['min' => 2, 'max' => 3],
                    'programas_esperados' => ['min' => 8, 'max' => 14],
                ],
                'requires_tracking' => true,
            ],
        );

        DocumentType::updateOrCreate(
            ['slug' => self::ANUAL],
            [
                'name' => 'Ruta anual completa',
                'delivery' => 'archivo_descargable',
                'requires_membership' => true,
                'layers' => ['itinerario', 'inventario'],
                'composition_policy' => [
                    'meses' => 12,
                    'balance' => 'horas',
                    'secuencia' => ['fundamentos', 'herramientas', 'certificacion'],
                    'mes_1' => 'especializacion_ancla',
                    'inventario' => 'catalogo_completo',
                    // Autogestivo: la secuencia se redacta como recomendación
                    // flexible, nunca como compromiso de fechas.
                    'tono' => 'recomendacion_flexible',
                ],
                'requires_tracking' => false,
            ],
        );
    }
}
