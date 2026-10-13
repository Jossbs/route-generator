<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El embudo se queda en nombre y cursos.
 *
 * La captura se reduce a lo que de verdad decide la estrategia: el cúmulo de
 * cursos de una rama con el que se engancha al lead. Ángulo comercial, nombre
 * público y vigencia dejan de pedirse —siguen siendo campos legítimos de una
 * campaña, y el día que Mercadotecnia los administre se vuelven a exponer sin
 * migrar— pero no se inventan: quedan nulos hasta que alguien los capture.
 *
 * `starts_on` nula significa que el embudo no tiene fecha de arranque, así que
 * está disponible desde que existe. `isInEffect()` lo interpreta así.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->string('public_name')->nullable()->change();
            $table->string('angle')->nullable()->change();
            $table->date('starts_on')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            $table->string('public_name')->nullable(false)->change();
            $table->string('angle')->nullable(false)->change();
            $table->date('starts_on')->nullable(false)->change();
        });
    }
};
