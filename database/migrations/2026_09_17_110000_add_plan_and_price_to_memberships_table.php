<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que se vendió, asentado donde se vendió.
 *
 * `plan` pasa a guardar un valor de `MembershipPlan` en vez de lo que el asesor
 * tecleara, y `plan_other` recoge lo negociado fuera de la lista publicada. Son
 * dos columnas por la misma razón que `leads.area` y `leads.area_other`: mezclarlas
 * obligaría a adivinar en cada lectura si lo que hay es vocabulario o texto de
 * alguien.
 *
 * `price` es el hueco que faltaba. La membresía se cerraba sin dejar rastro de por
 * cuánto, así que el sistema sabía qué se entregó pero no qué se cobró, y el dato
 * solo existía en la conversación de WhatsApp del asesor.
 *
 * **El precio no viaja al documento.** No entra al snapshot, que es lo que alimenta
 * al PDF: lo que el payload no carga, la plantilla no puede imprimir por descuido.
 * El documento cuantifica horas, programas y constancias DC-3; el precio lo dice el
 * asesor en la conversación, donde puede atender objeciones (docs/ARQUITECTURA.md §4.5).
 *
 * Nullable en la base aunque la captura lo exija: las membresías dadas de alta
 * antes de esta migración existen y no se les puede inventar un importe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table): void {
            $table->string('plan_other', 120)->nullable()->after('plan');
            // 10,2 alcanza para un plan Business cotizado; el importe es en pesos.
            $table->decimal('price', 10, 2)->nullable()->after('plan_other');
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table): void {
            $table->dropColumn(['plan_other', 'price']);
        });
    }
};
