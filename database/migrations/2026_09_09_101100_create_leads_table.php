<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El lead es entidad propia desde el primer día. `external_id` nullable es la
 * costura con Kommo: cuando llegue, no hay migración de datos.
 *
 * unique(source, external_id) no estorba a los leads de formulario porque en
 * PostgreSQL los NULL no colisionan en un índice único.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->enum('source', ['formulario', 'kommo'])->default('formulario');
            $table->string('external_id')->nullable();
            $table->foreignId('requested_specialization_id')->nullable()->constrained('specializations')->nullOnDelete();
            $table->foreignId('archetype_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('advisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['prospecto', 'miembro'])->default('prospecto');
            $table->jsonb('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index('advisor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
