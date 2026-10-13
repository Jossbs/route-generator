<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La ruta curada dentro del embudo. Un embudo puede llevar varias, cada una
 * dirigida a un arquetipo distinto: la campaña de octubre con una de Calidad, otra
 * de Lean y otra de Six Sigma.
 *
 * `archetype_id` apunta a `archetypes` y no a una tabla nueva de perfiles: esa
 * entidad ya existe, ya está cableada a las tablas de afinidad que lee el
 * compositor, y duplicarla dejaría dos vocabularios para el mismo concepto. El
 * asesor lo elige de una lista; la detección automática sigue fuera de la v1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnel_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('funnel_id')->constrained('funnels')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('position')->default(0);
            $table->foreignId('archetype_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('main_specialization_id')->nullable()->constrained('specializations')->nullOnDelete();
            $table->unsignedTinyInteger('duration_months')->default(12);
            // Override del copy de catálogo para esta promoción concreta.
            $table->text('intro_text')->nullable();
            $table->text('closing_text')->nullable();

            // --- dormido: mecánica «elige N de M» ---
            // El sitio comercial histórico ya opera con planes de «3 cursos a
            // elegir». Esa mecánica va a regresar; la columna cuesta cero hoy y
            // evita una migración incómoda después. La lógica no se construye.
            $table->unsignedTinyInteger('choose_n')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['funnel_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_routes');
    }
};
