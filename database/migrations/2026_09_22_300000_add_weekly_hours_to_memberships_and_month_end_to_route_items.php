<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * El ritmo de estudio del miembro, capturado por el asesor (2026-09-22).
 *
 * `weekly_hours` es lo que el lead dijo que puede estudiar a la semana. Decide
 * cuántos cursos caben en su año y dónde termina cada mes, y va impreso: el
 * documento dice «a N h/semana» con su número, no con uno de referencia. Nullable
 * porque las membresías anteriores no lo tienen; esas conservan las 15 h fijas.
 *
 * `month_end` es el mes en que termina un curso que no cabe en uno solo a ese
 * ritmo: 40 h a 3 h/semana abarcan tres meses. `month` sigue siendo el mes en que
 * empieza, que es el que ordena la lectura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->unsignedSmallInteger('weekly_hours')->nullable()->after('ends_on');
        });

        Schema::table('route_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('month_end')->nullable()->after('month');
        });
    }

    public function down(): void
    {
        Schema::table('route_items', function (Blueprint $table) {
            $table->dropColumn('month_end');
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn('weekly_hours');
        });
    }
};
