<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una ruta emitida se puede corregir, y eso hay que poder contarlo.
 *
 * La bandeja pasó a ser administrable: el asesor corrige el nombre que escribió
 * mal, la vigencia que le dictaron de otro modo o los dos cursos que recomendó
 * de más, y el documento se vuelve a emitir sobre el mismo renglón. Corregir en
 * sitio es deliberado —decidido el 2026-09-17— y tiene un costo que este par de
 * columnas hace visible en vez de esconder: el snapshot que queda es el último,
 * no el que vio el lead la primera vez. `revisions` dice cuántas veces se pisó y
 * `revised_at` cuándo fue la última, que es lo que la bandeja muestra donde antes
 * iba el estado.
 *
 * `deleted_at` es borrado suave a propósito. Una ruta arrastra sus validaciones y
 * su bitácora, que son la prueba de que las restricciones de docs/ARQUITECTURA.md §4 se
 * evaluaron; borrarlas de verdad dejaría el expediente sin con qué demostrarlo.
 * Lo que sí desaparece al borrar es lo que está de cara al lead: el renglón sale
 * de la bandeja, el enlace deja de abrir y el archivo se va del disco.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_routes', function (Blueprint $table) {
            $table->unsignedSmallInteger('revisions')->default(0)->after('content_unit_count');
            $table->timestamp('revised_at')->nullable()->after('revisions');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('learning_routes', function (Blueprint $table) {
            $table->dropColumn(['revisions', 'revised_at', 'deleted_at']);
        });
    }
};
