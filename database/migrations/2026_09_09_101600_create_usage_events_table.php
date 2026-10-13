<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de uso. `abierta_por_lead` es la única traza que deja la ruta enfocada
 * después de salir por WhatsApp; la ruta anual se descarga y ahí termina la visibilidad.
 * La IP se guarda hasheada: no necesitamos la dirección, solo distinguir aperturas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('learning_routes')->cascadeOnDelete();
            $table->foreignId('advisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('event', ['generada', 'rechazada', 'renderizada', 'abierta_por_lead']);
            $table->string('ip_hash', 64)->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamp('created_at');

            $table->index(['route_id', 'event']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
