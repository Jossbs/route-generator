<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La bitácora del centro de confianza, el sitio estático que el asesor manda
 * cuando el lead duda.
 *
 * Va aparte de `usage_events` porque no nace de una ruta: casi todas las visitas
 * son anónimas. Cuando el enlace trae el token de la ruta inicial (`?l=`), el
 * evento se liga a esa ruta y a su lead, y ahí sí se sabe quién leyó qué.
 *
 * `advisor_key` es la clave del asesor tal como viene en el enlace (`?a=`). Se
 * guarda como texto y no como llave foránea: la resuelve el `asesores.json` del
 * sitio, no esta base, y un asesor que todavía no existe aquí igual debe medirse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trust_events', function (Blueprint $table) {
            $table->id();
            $table->string('visit_id', 36);
            $table->enum('event', ['abierta', 'seccion_vista', 'pregunta_abierta', 'clic']);
            $table->string('target', 60)->nullable();
            $table->string('advisor_key', 40)->nullable();
            $table->foreignId('advisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('route_id')->nullable()->constrained('learning_routes')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('ip_hash', 64)->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamp('created_at');

            $table->index(['visit_id', 'event']);
            $table->index(['lead_id', 'created_at']);
            $table->index(['advisor_key', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trust_events');
    }
};
