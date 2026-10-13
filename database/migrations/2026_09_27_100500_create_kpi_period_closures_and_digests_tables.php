<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El cierre de mes y los resúmenes enviados.
 *
 * Cerrar un mes congela sus números en `snapshot` —lo que se reportó y sobre lo
 * que se pagará comisión cuando llegue la segunda etapa— y a partir de ahí todo
 * cambio sobre ese mes entra como ajuste con motivo.
 *
 * `kpi_digests` guarda cada resumen diario o semanal tal como se mandó a
 * Telegram, para verlo también dentro del módulo y para saber si falló el envío.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_period_closures', function (Blueprint $table) {
            $table->id();
            $table->date('period_start')->unique();
            $table->jsonb('snapshot');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at');
            $table->timestamps();
        });

        Schema::create('kpi_digests', function (Blueprint $table) {
            $table->id();
            $table->string('kind');
            $table->date('period_start');
            $table->date('period_end');
            $table->text('body');
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['kind', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_digests');
        Schema::dropIfExists('kpi_period_closures');
    }
};
