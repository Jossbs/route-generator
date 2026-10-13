<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién es quién en ventas, y desde cuándo.
 *
 * La pertenencia a un equipo y el papel van con fechas porque cambian: la venta
 * de marzo cuenta para el equipo de marzo aunque el asesor se haya movido en
 * abril (decidido el 2026-09-26). Por eso el equipo de un evento no se guarda en
 * el evento: se resuelve contra estos tramos en la fecha en que ocurrió.
 *
 * `is_kpi_admin` es la única distinción de acceso del módulo mientras el sistema
 * anfitrión no traiga sus roles: quien captura eventos, fija metas y cierra
 * periodos. El tablero lo ven todos (ranking abierto, 2026-09-26).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_teams', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('sales_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Nulo para quien no pertenece a un equipo: dirección, típicamente.
            $table->foreignId('sales_team_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('role');
            $table->date('starts_on');
            // Nulo = vigente.
            $table->date('ends_on')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'starts_on']);
            $table->index(['sales_team_id', 'starts_on']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_kpi_admin')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_kpi_admin'));
        Schema::dropIfExists('sales_assignments');
        Schema::dropIfExists('sales_teams');
    }
};
