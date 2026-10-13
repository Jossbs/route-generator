<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El copy del PDF es catálogo, no plantilla. Si vive dentro de Blade, las
 * restricciones sobre texto (CORE_TOOLS_PARTIAL, NO_CERT_EQUIVALENCE) son
 * inverificables.
 *
 * `body` es nullable: en v1 se siembran los huecos que el documento necesita,
 * sin texto, para que Contenido los redacte. No se inventa copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copy_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->enum('scope', ['portada', 'intro_especializacion', 'cierre', 'frase_puente']);
            $table->string('locale', 10)->default('es_MX');
            $table->text('body')->nullable();
            $table->jsonb('claims')->nullable();
            $table->jsonb('applies_when')->nullable();
            $table->enum('status', ['pendiente', 'borrador', 'aprobado'])->default('pendiente');
            $table->timestamps();

            $table->index(['scope', 'status']);
        });

        Schema::create('banned_phrases', function (Blueprint $table) {
            $table->id();
            $table->text('pattern');
            $table->string('scope');
            $table->text('reason');
            $table->text('suggested_replacement')->nullable();
            $table->enum('severity', ['blocking', 'warning'])->default('blocking');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['scope', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banned_phrases');
        Schema::dropIfExists('copy_blocks');
    }
};
