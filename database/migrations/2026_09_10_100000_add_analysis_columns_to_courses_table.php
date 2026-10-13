<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La capa de análisis del catálogo: lo que el generador necesita para ordenar una
 * ruta y lo que la auditoría necesita para defenderla.
 *
 * `complexity_*` es derivado, no capturado: `ComplexityRubric` lo recalcula desde
 * horas, lecciones y los dos criterios de juicio. Queda nullable porque tres
 * cursos no publican duración y un nivel inventado sería peor que ninguno.
 *
 * `allowed_layers` es la lista blanca de capas del curso, hermana de
 * `specializations.allowed_roles`: null = todas. Es dato, no condicional, para que
 * mover un curso entre itinerario e inventario sea un UPDATE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->text('short_description')->nullable()->after('name');
            $table->decimal('complexity_index', 3, 2)->nullable()->after('duration_hours');
            $table->unsignedTinyInteger('complexity_level')->nullable()->after('complexity_index');
            $table->jsonb('complexity_criteria')->nullable()->after('complexity_level');
            $table->jsonb('allowed_layers')->nullable()->after('complexity_criteria');
            $table->boolean('needs_overlap_review')->default(false)->after('allowed_layers');
            $table->boolean('needs_duration_review')->default(false)->after('needs_overlap_review');
            $table->text('analysis_note')->nullable()->after('needs_duration_review');

            $table->index(['complexity_level', 'specialization_id']);
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['complexity_level', 'specialization_id']);
            $table->dropColumn([
                'short_description',
                'complexity_index',
                'complexity_level',
                'complexity_criteria',
                'allowed_layers',
                'needs_overlap_review',
                'needs_duration_review',
                'analysis_note',
            ]);
        });
    }
};
