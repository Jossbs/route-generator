<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v1 siembra un solo arquetipo `general`, marcado como placeholder.
 * Los 8 reales entran con el Excel sin tocar esquema ni código.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archetypes', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('motivation')->nullable();
            $table->foreignId('bridge_phrase_block_id')->nullable()->constrained('copy_blocks')->nullOnDelete();
            $table->boolean('is_placeholder')->default(false);
            $table->timestamps();
        });

        Schema::create('archetype_specialization_affinity', function (Blueprint $table) {
            $table->foreignId('archetype_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialization_id')->constrained()->cascadeOnDelete();
            $table->decimal('weight', 4, 3);
            $table->string('suggested_role')->nullable();

            $table->primary(['archetype_id', 'specialization_id'], 'archetype_specialization_affinity_primary');
        });

        Schema::create('archetype_tag_affinity', function (Blueprint $table) {
            $table->foreignId('archetype_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->decimal('weight', 4, 3);

            $table->primary(['archetype_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archetype_tag_affinity');
        Schema::dropIfExists('archetype_specialization_affinity');
        Schema::dropIfExists('archetypes');
    }
};
