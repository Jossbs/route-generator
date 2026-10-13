<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las metas, por medida, por alcance y por periodo, con su historia.
 *
 * Se fijan por mes o por trimestre y no se dividen parejo: el trimestre puede
 * ser 10 + 20 + 30 = 60. La semana y el día no se capturan: salen del mes,
 * repartido por día natural porque ventas trabaja los siete (2026-09-26).
 *
 * Cambiar una meta no pisa la anterior: la cierra con `replaced_at` y crea otra,
 * con autor y motivo. El tablero usa la vigente; la historia explica por qué el
 * reporte de hace dos semanas decía otra cosa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_measure_id')->constrained()->cascadeOnDelete();
            $table->string('scope');
            $table->foreignId('sales_team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('period_type');
            $table->date('period_start');
            $table->decimal('value', 14, 2);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replaced_at')->nullable();
            $table->timestamps();

            $table->index(['kpi_measure_id', 'scope', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_targets');
    }
};
