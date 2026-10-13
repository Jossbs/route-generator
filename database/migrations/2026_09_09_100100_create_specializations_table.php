<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las 12 especializaciones del catálogo.
 *
 * `allowed_roles` resuelve como dato la restricción SUPPLY_CHAIN_ROLE: Cadena de
 * Suministros conserva su identidad y su encabezado, pero no puede ocupar rol de
 * ancla ni de complementaria en la ruta enfocada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specializations', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('display_order');
            $table->enum('completeness_status', ['complete', 'partial'])->default('complete');
            $table->jsonb('missing_components')->nullable();
            $table->jsonb('allowed_roles');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('display_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('specializations');
    }
};
