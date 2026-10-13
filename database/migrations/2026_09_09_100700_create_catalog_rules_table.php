<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de reglas. Cada restricción de dominio es una fila, no un condicional.
 *
 * `type` no es enum a propósito: el registro de intérpretes es la fuente de verdad
 * y un tipo nuevo exige código de todos modos.
 *
 * Los miembros de una regla viven en tablas con llave foránea, nunca dentro del
 * JSONB: borrar un curso no debe dejar una regla apuntando al vacío.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type');
            $table->enum('severity', ['blocking', 'warning'])->default('blocking');
            $table->jsonb('applies_to_document_types')->nullable();
            $table->jsonb('applies_to_roles')->nullable();
            $table->jsonb('parameters')->nullable();
            $table->text('message_es');
            $table->text('source_note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('effective_from')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'type']);
        });

        Schema::create('rule_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('catalog_rules')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member');

            $table->unique(['rule_id', 'course_id', 'role']);
        });

        Schema::create('rule_specializations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('catalog_rules')->cascadeOnDelete();
            $table->foreignId('specialization_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('subject');

            $table->unique(['rule_id', 'specialization_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_specializations');
        Schema::dropIfExists('rule_courses');
        Schema::dropIfExists('catalog_rules');
    }
};
