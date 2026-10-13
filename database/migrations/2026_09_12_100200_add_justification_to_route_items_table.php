<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Por qué este bloque está en la ruta.
 *
 * `ruta_cursos.justificacion` del Diccionario de Datos §2, marcada `[INTERNO]`:
 * la lee el asesor en la pantalla de propuesta —«Porque trabaja en automotriz»,
 * «Es lo que va a usar el lunes en el piso»— y nunca el lead. Que el asesor pueda
 * defender cada bloque en la conversación es la mitad del valor del documento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_items', function (Blueprint $table) {
            $table->string('justification', 200)->nullable()->after('counts_toward_total');
        });
    }

    public function down(): void
    {
        Schema::table('route_items', function (Blueprint $table) {
            $table->dropColumn('justification');
        });
    }
};
