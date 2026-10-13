<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los dos slots de especialidad complementaria.
 *
 * El Diccionario de Datos §2 (DOMINIO C) los define como
 * `especialidad_comp_1_id` y `_2_id`, «complementarias elegidas por el asesor».
 * Existen como columnas y no solo como renglones de `route_items` porque son una
 * decisión, no un resultado: el motor propone dos, el asesor puede cambiarlas, y
 * lo que se guarda es lo que él dejó.
 *
 * Es también lo que permite responder «¿qué complementarias se ofrecieron más y
 * cuáles convirtieron?» sin abrir el snapshot de cada documento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_routes', function (Blueprint $table) {
            $table->foreignId('complementary_1_specialization_id')
                ->nullable()->after('anchor_specialization_id')
                ->constrained('specializations')->nullOnDelete();

            $table->foreignId('complementary_2_specialization_id')
                ->nullable()->after('complementary_1_specialization_id')
                ->constrained('specializations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('learning_routes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('complementary_2_specialization_id');
            $table->dropConstrainedForeignId('complementary_1_specialization_id');
        });
    }
};
