<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convierte "restricción respetada" en algo auditable después del hecho.
 * Se guarda `rule_code` y no una FK: la bitácora debe sobrevivir a que la regla
 * se desactive o se reescriba.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('learning_routes')->cascadeOnDelete();
            $table->string('rule_code');
            $table->enum('result', ['pass', 'fail', 'skip']);
            $table->jsonb('details')->nullable();
            $table->timestamp('evaluated_at');

            $table->index(['route_id', 'result']);
            $table->index('rule_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_validations');
    }
};
