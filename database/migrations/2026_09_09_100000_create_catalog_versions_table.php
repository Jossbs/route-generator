<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Versiones del catálogo. v1 = sitio web (64 cursos), v2 = el Excel cuando llegue.
 * Las rutas guardan contra qué versión se generaron para poder reproducirlas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_versions', function (Blueprint $table) {
            $table->id();
            $table->string('label')->unique();
            $table->enum('source', ['sitio_web', 'excel']);
            $table->timestamp('published_at')->nullable();
            $table->string('checksum')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_versions');
    }
};
