<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $texts = [
            'saber.modalidad' => [
                'body' => 'Estudias en línea y a tu ritmo: acceso 24/7, clases en video con instructores y material de apoyo para reforzar todo aprendizaje.',
                'claims' => ['Clases en video y material de apoyo confirmados por el usuario el 2026-11-08.'],
            ],
            'confianza.entradilla' => [
                'body' => 'Desde 2018 impulsamos el desarrollo de profesionales con formación en ingeniería. Una comunidad internacional, experiencia en empresas y respaldos que puedes consultar.',
                'claims' => ['Fundación en 2018: linkedin.com/company/desarrollo-profesional-sys/. Comunidad, empresas y respaldos: academy.dpsys.com.mx/home. Consultados el 2026-11-08.'],
            ],
            'confianza.cifra.google' => [
                'body' => '2018 Fundación',
                'claims' => ['Año de fundación publicado en linkedin.com/company/desarrollo-profesional-sys/. El indicador sustituye la calificación de Google que se retiró de la hoja institucional.'],
            ],
        ];

        foreach ($texts as $slug => $text) {
            DB::table('copy_blocks')->where('slug', $slug)->update([
                'body' => $text['body'],
                'claims' => json_encode($text['claims'], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // La corrección editorial solicitada no se revierte al deshacer esquema.
    }
};
