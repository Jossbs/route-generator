<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El área del lead, en dos columnas que no son lo mismo.
 *
 * `area` guarda uno de los catorce valores de `LeadArea`: es el dato tipificado,
 * el que se puede contar y el que pesa en qué cursos se recomiendan. `area_other`
 * guarda el área que no estaba en la lista, ya estandarizada por AreaNormalizer.
 *
 * Son dos columnas y no una porque mezclarlas obligaría a adivinar en cada
 * lectura si lo que hay es un valor del vocabulario o texto de alguien. Nunca
 * están llenas las dos a la vez: lo que se reconoce sube a `area`.
 *
 * El índice es sobre `area` porque es sobre lo que se va a segmentar; el texto
 * libre se lee de una ficha, no se agrupa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('area', 40)->nullable()->after('occupation');
            $table->string('area_other', 80)->nullable()->after('area');

            $table->index('area');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['area']);
            $table->dropColumn(['area', 'area_other']);
        });
    }
};
