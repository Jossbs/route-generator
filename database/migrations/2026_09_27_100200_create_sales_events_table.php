<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los hechos: cada venta, cobro o acción, uno por renglón.
 *
 * Es lo único que se captura. Los totales no se guardan: se calculan, y por eso
 * el mismo evento sirve para el KPI de hoy y para cualquier métrica que se
 * invente después —por plan, por día de la semana, por equipo— (decidido el
 * 2026-09-26).
 *
 * `user_id` es el asesor al que se le acredita, siempre uno: el crédito es
 * completo del asesor aunque haya auxiliar (2026-09-26).
 *
 * Nada se borra. Un error se anula (`voided_*`) con motivo, y un cambio sobre un
 * mes ya cerrado entra marcado como ajuste: lo que ya se reportó tiene que poder
 * explicarse después.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_event_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('occurred_on');
            $table->jsonb('values')->default('{}');
            $table->text('notes')->nullable();

            $table->string('source')->default('manual');
            $table->string('external_id')->nullable();
            $table->string('status')->default('confirmado');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();

            // El último cambio hecho con el mes ya cerrado, con su motivo.
            $table->boolean('is_adjustment')->default(false);
            $table->text('adjustment_reason')->nullable();

            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();

            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index(['sales_event_type_id', 'occurred_on']);
            $table->index(['user_id', 'occurred_on']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_events');
    }
};
