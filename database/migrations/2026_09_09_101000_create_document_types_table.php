<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los tipos de documento son datos. Un tercer documento (renovación, reactivación)
 * debe ser un INSERT, no un deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->enum('delivery', ['enlace_rastreado', 'archivo_descargable']);
            $table->boolean('requires_membership')->default(false);
            $table->jsonb('layers');
            $table->jsonb('composition_policy');
            $table->boolean('requires_tracking')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
