<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doble representación deliberada: `learning_routes.snapshot` es la verdad
 * histórica; `route_items` existe para consultar y medir sin abrir JSON.
 *
 * `counts_toward_total` vive aquí y no en `courses` porque contar depende de qué
 * más está en la ruta: si la ruta solo lleva Excel Completo, ese curso sí cuenta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('learning_routes')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('specialization_id')->constrained()->restrictOnDelete();
            $table->enum('layer', ['itinerario', 'inventario']);
            $table->enum('role', ['ancla', 'complementaria', 'refuerzo', 'incluido']);
            $table->unsignedTinyInteger('month')->nullable();
            $table->unsignedSmallInteger('position');
            $table->boolean('counts_toward_total')->default(true);
            $table->timestamps();

            $table->unique(['route_id', 'layer', 'course_id']);
            $table->index(['route_id', 'layer', 'month', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_items');
    }
};
