<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los 55 programas de contenido distintos. Es la unidad que se cuenta.
 * Los cuatro cursos de Excel apuntan a una sola: por eso 64 cursos ≠ 64 programas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_units', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->foreignId('specialization_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_units');
    }
};
