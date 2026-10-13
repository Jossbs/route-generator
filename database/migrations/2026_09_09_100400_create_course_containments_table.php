<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué curso "Completo" absorbe a qué nivel. `containment_type = parcial` cuando las
 * horas del paquete no cuadran con la suma de sus niveles (auditoría pendiente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_containments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('child_course_id')->constrained('courses')->cascadeOnDelete();
            $table->enum('containment_type', ['equivalente', 'parcial']);
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['parent_course_id', 'child_course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_containments');
    }
};
