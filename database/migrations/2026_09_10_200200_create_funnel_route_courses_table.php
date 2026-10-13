<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La composición de la ruta curada: pivote enriquecido con orden y mes sugerido.
 *
 * `suggested_month` es sugerencia y nunca fecha: los cursos son autogestivos con
 * acceso 24/7, así que el documento dice «mes 1 sugerido» y la ruta se redacta
 * como recomendación de secuencia, no como compromiso de calendario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnel_route_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_route_id')->constrained('funnel_routes')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedTinyInteger('suggested_month')->nullable();

            // --- dormidos ---
            // `kind` acompaña a funnel_routes.choose_n: fijo | elegible.
            $table->string('kind')->default('fijo');
            // Válvula de escape para que una promoción lleve su propia frase sin
            // que nadie fabrique contenido en el catálogo maestro. Las 64 frases
            // de resultado siguen sin aprobarse: la columna existe y se queda
            // vacía. Sigue prohibido inventarlas.
            $table->text('outcome_phrase_override')->nullable();

            $table->timestamps();

            $table->unique(['funnel_route_id', 'course_id']);
            $table->index(['funnel_route_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_route_courses');
    }
};
