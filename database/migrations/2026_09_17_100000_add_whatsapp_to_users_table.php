<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El WhatsApp del asesor, para que el documento devuelva la conversación.
 *
 * El cierre del gancho dice «respóndeme por WhatsApp» y hasta ahora no había a
 * dónde responder: el lead tenía que salir del documento, buscar el chat y
 * volver. Un enlace `wa.me` cierra esa fricción en un toque, que es justo donde
 * se pierde una conversión.
 *
 * Nullable a propósito. El perfil del asesor lo administra el sistema anfitrión
 * —aquí no hay pantallas de cuenta, docs/ARQUITECTURA.md §5— así que este dato puede llegar
 * tarde o no llegar. Sin él, el documento simplemente no pinta el botón y se lee
 * igual; nunca imprime un enlace roto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('whatsapp', 40)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('whatsapp');
        });
    }
};
