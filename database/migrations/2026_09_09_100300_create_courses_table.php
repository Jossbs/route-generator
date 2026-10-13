<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los 64 cursos publicados.
 *
 * `lessons_count` y `duration_hours` son nullable: el sitio no publica los datos de
 * Mente Millonaria, Liderazgo Efectivo ni Estrategias de Negociación.
 * `outcome_phrase` arranca en NULL hasta que Contenido apruebe las 64 frases.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->foreignId('specialization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_unit_id')->constrained()->cascadeOnDelete();
            $table->enum('level', ['basico', 'intermedio', 'avanzado', 'completo', 'unico']);
            $table->unsignedSmallInteger('lessons_count')->nullable();
            $table->unsignedSmallInteger('duration_hours')->nullable();
            $table->text('outcome_phrase')->nullable();
            $table->enum('outcome_phrase_status', ['borrador', 'aprobado'])->default('borrador');
            $table->string('delivery_mode')->default('autogestivo');
            $table->string('credential_type')->default('dc3_stps');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['specialization_id', 'level']);
            $table->index('content_unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
