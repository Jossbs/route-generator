<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los KPIs y sus medidas, como datos.
 *
 * Un KPI es un nombre; lo que se mide son sus medidas. «Promos vendidas» tiene
 * piezas y dinero, y cada una lleva su propia meta y su propio avance (decidido
 * el 2026-09-26).
 *
 * Una medida es agregada —cuenta, suma, promedia, mínimo o máximo de un campo de
 * un tipo de evento, con filtros— o fórmula, que combina otras dos: razón,
 * diferencia o suma. Con eso se expresan la conversión, el ticket promedio y la
 * venta neta sin escribir código.
 *
 * Las medidas de una fórmula van por llave foránea y no dentro del JSONB, por la
 * misma razón que `rule_courses` (docs/ARQUITECTURA.md §8): borrar una medida no debe dejar
 * una fórmula apuntando al vacío. El JSONB guarda solo los filtros.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpis', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('kpi_measures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->string('unit');
            $table->string('kind');

            // --- agregado ---
            $table->foreignId('sales_event_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('aggregation')->nullable();
            $table->string('field_key')->nullable();
            // [{field, op, value}]
            $table->jsonb('filters')->default('[]');

            // --- fórmula ---
            $table->string('formula_operator')->nullable();
            $table->foreignId('left_measure_id')->nullable()->constrained('kpi_measures')->restrictOnDelete();
            $table->foreignId('right_measure_id')->nullable()->constrained('kpi_measures')->restrictOnDelete();

            $table->boolean('has_target')->default(true);
            // Falso para lo que conviene que baje: cancelaciones, reembolsos.
            $table->boolean('higher_is_better')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['kpi_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_measures');
        Schema::dropIfExists('kpis');
    }
};
