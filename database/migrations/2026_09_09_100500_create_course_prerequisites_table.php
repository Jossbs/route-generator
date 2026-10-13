<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Precedencia pedagógica. `duro` la valida LEVEL_SEQUENCE; `sugerido` solo orienta
 * al generador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_prerequisites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('requires_course_id')->constrained('courses')->cascadeOnDelete();
            $table->enum('strength', ['duro', 'sugerido']);
            $table->timestamps();

            $table->unique(['course_id', 'requires_course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_prerequisites');
    }
};
