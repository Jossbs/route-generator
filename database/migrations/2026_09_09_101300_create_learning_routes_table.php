<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `snapshot` es la verdad histórica reproducible: si el catálogo cambia en tres
 * meses, el PDF que recibió el lead sigue siendo el mismo.
 *
 * `access_token` solo se emite para la ruta enfocada. `parent_route_id` liga el
 * documento anual al gancho que lo originó: con los timestamps, la conversión
 * gancho → membresía es una consulta, no un evento aparte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_route_id')->nullable()->constrained('learning_routes')->nullOnDelete();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('advisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('anchor_specialization_id')->nullable()->constrained('specializations')->nullOnDelete();
            $table->foreignId('archetype_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['draft', 'validated', 'rejected', 'rendering', 'rendered', 'failed'])->default('draft');
            $table->foreignId('catalog_version_id')->constrained()->restrictOnDelete();
            $table->jsonb('snapshot')->nullable();
            $table->unsignedSmallInteger('course_count')->default(0);
            $table->unsignedSmallInteger('content_unit_count')->default(0);
            $table->string('access_token', 64)->nullable()->unique();
            $table->string('pdf_path')->nullable();
            $table->timestamp('rendered_at')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'status']);
            $table->index('parent_route_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_routes');
    }
};
