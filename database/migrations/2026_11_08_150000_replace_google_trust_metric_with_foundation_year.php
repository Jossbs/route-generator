<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('copy_blocks')->where('slug', 'confianza.cifra.google')->update([
            'body' => '2018 Fundación',
            'claims' => json_encode([
                'Año de fundación publicado en linkedin.com/company/desarrollo-profesional-sys/. El indicador sustituye la calificación de Google que se retiró de la hoja institucional.',
            ], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Se conserva el dato editorial vigente en caso de rollback.
    }
};
