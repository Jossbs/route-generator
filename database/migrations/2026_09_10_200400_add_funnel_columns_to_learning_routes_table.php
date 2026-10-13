<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El origen de la ruta emitida y el sello del snapshot.
 *
 * No hay tabla aparte para las rutas que salen de un embudo: el embudo es un
 * preset que siembra al motor, no un motor paralelo. Las dos formas de armar una
 * ruta —el asesor llena el formulario, o el asesor elige un embudo que precarga
 * cursos— desembocan en la misma tabla, el mismo validador y el mismo snapshot.
 * `funnel_id` nulo significa ruta libre, que es el flujo que ya existía.
 *
 * `snapshot_hash` es sha256 sobre el JSON del payload. El documento vive en una
 * URL permanente: si el hash deja de cuadrar, el snapshot se tocó después de
 * emitirse y el PDF que vio el lead ya no es el que está guardado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_routes', function (Blueprint $table) {
            $table->foreignId('funnel_id')->nullable()->after('archetype_id')->constrained('funnels')->nullOnDelete();
            $table->foreignId('funnel_route_id')->nullable()->after('funnel_id')->constrained('funnel_routes')->nullOnDelete();
            $table->string('snapshot_hash', 64)->nullable()->after('snapshot');

            $table->index('funnel_id');
        });
    }

    public function down(): void
    {
        Schema::table('learning_routes', function (Blueprint $table) {
            $table->dropIndex(['funnel_id']);
            $table->dropConstrainedForeignId('funnel_route_id');
            $table->dropConstrainedForeignId('funnel_id');
            $table->dropColumn('snapshot_hash');
        });
    }
};
