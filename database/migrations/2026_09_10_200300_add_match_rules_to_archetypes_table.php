<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El hueco reservado para la detección automática de arquetipo, que sigue fuera
 * del alcance de la v1 por decisión previa del proyecto.
 *
 * Se crea la columna y se detiene ahí: el asesor elige el arquetipo de una lista y
 * `match_rules` queda vacía. Tenerla evita una migración el día que se decida
 * detectarlo; construir lógica alrededor reabriría un alcance ya cerrado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archetypes', function (Blueprint $table) {
            $table->jsonb('match_rules')->nullable()->after('is_placeholder');
        });
    }

    public function down(): void
    {
        Schema::table('archetypes', function (Blueprint $table) {
            $table->dropColumn('match_rules');
        });
    }
};
