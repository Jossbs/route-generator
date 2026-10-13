<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los dos campos de la captura que alimentan la composición.
 *
 * No son datos de contacto: son la materia prima de la ruta. La carcasa lo dice
 * en la ayuda de cada campo —«de aquí salen los cursos que te voy a proponer» y
 * «lo uso para escoger entre cursos parecidos»— y sin ellos el generador propone
 * lo mismo para un ingeniero de una automotriz que para una coordinadora de
 * calidad de un laboratorio.
 *
 * `occupation` también viaja a `memberships` cuando el lead compra, porque ahí se
 * imprime en la portada; aquí vive la que se capturó antes de la venta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('occupation')->nullable()->after('email');
            $table->text('context')->nullable()->after('occupation');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['occupation', 'context']);
        });
    }
};
