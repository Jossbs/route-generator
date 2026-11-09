<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Primero las tablas dependientes, sin desactivar las llaves foráneas.
        // dropIfExists permite actualizar instalaciones existentes y bases nuevas.
        foreach ([
            'kpi_digests',
            'kpi_period_closures',
            'kpi_targets',
            'kpi_measures',
            'kpis',
            'sales_events',
            'sales_event_fields',
            'sales_event_types',
            'sales_assignments',
            'sales_teams',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        if (Schema::hasColumn('users', 'is_kpi_admin')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_kpi_admin'));
        }
    }

    public function down(): void
    {
        throw new LogicException('La eliminación del módulo de métricas de ventas requiere restaurar un respaldo para revertirse.');
    }
};
