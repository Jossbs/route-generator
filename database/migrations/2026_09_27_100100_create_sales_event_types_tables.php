<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los tipos de evento y sus campos, como datos.
 *
 * Un tipo de evento es cualquier cosa que ventas registre —una venta, un cobro,
 * una cancelación, un lead atendido— y sus campos son lo que se captura de cada
 * uno. Un dato nuevo es una fila en `sales_event_fields`, no una columna ni un
 * deploy: los KPIs son variables y el módulo tiene que estar listo para
 * cualquiera (decidido el 2026-09-26).
 *
 * `key` es el nombre del campo dentro de `sales_events.values`. Se fija al
 * crearlo y no se renombra: los filtros y las medidas lo referencian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_event_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sales_event_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_event_type_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('data_type');
            // Solo para `opcion`: [{value, label}].
            $table->jsonb('options')->default('[]');
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['sales_event_type_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_event_fields');
        Schema::dropIfExists('sales_event_types');
    }
};
