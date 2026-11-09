<?php

declare(strict_types=1);

use App\Enums\MembershipPlan;
use App\Models\Lead;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('elimina el esquema anterior con sus datos y conserva usuarios y membresías', function () {
    $advisor = User::factory()->create();
    $lead = Lead::create(['name' => 'María López']);
    $membership = Membership::create([
        'lead_id' => $lead->id,
        'full_name' => $lead->name,
        'occupation' => 'Ingeniera de calidad',
        'plan' => MembershipPlan::ExpertAnual,
        'price' => 4199,
        'starts_on' => '2026-11-08',
        'ends_on' => '2027-11-08',
        'created_by' => $advisor->id,
    ]);

    // Reproduce las dependencias de una instalación anterior con datos.
    $tables = [
        'sales_teams' => [],
        'sales_assignments' => ['user_id' => 'users', 'sales_team_id' => 'sales_teams'],
        'sales_event_types' => [],
        'sales_event_fields' => ['sales_event_type_id' => 'sales_event_types'],
        'sales_events' => ['sales_event_type_id' => 'sales_event_types', 'user_id' => 'users'],
        'kpis' => [],
        'kpi_measures' => [
            'kpi_id' => 'kpis',
            'sales_event_type_id' => 'sales_event_types',
            'left_measure_id' => 'kpi_measures',
            'right_measure_id' => 'kpi_measures',
        ],
        'kpi_targets' => ['kpi_measure_id' => 'kpi_measures', 'sales_team_id' => 'sales_teams', 'user_id' => 'users'],
        'kpi_period_closures' => ['closed_by' => 'users'],
        'kpi_digests' => [],
    ];

    foreach ($tables as $name => $references) {
        Schema::create($name, function (Blueprint $table) use ($references) {
            $table->id();

            foreach ($references as $column => $target) {
                $table->foreignId($column)->constrained($target);
            }
        });

        DB::table($name)->insert([
            'id' => 1,
            ...array_map(fn (string $target): int => $target === 'users' ? $advisor->id : 1, $references),
        ]);
    }

    Schema::table('users', fn (Blueprint $table) => $table->boolean('is_kpi_admin')->default(true));

    $migration = require database_path('migrations/2026_11_08_100000_remove_sales_metrics_module.php');
    $migration->up();
    $migration->up(); // También funciona si ya no queda nada que retirar.

    foreach (array_keys($tables) as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }

    expect(Schema::hasColumn('users', 'is_kpi_admin'))->toBeFalse()
        ->and($advisor->fresh()?->email)->toBe($advisor->email)
        ->and($membership->fresh()?->price)->toBe('4199.00');

    // Crear y corregir membresías ya no requiere tablas de eventos de ventas.
    $membership->update(['price' => 4299]);
    $copy = $membership->replicate();
    $copy->save();

    expect($copy->fresh()?->price)->toBe('4299.00');
});

it('retira las pantallas y los comandos del módulo', function () {
    foreach (['/kpis', '/kpis/eventos', '/kpis/metas', '/kpis/cierres', '/kpis/configuracion', '/kpis/resumen.csv', '/kpis/reporte.pdf'] as $path) {
        $this->get($path)->assertNotFound();
    }

    expect(array_keys(Artisan::all()))->not->toContain('kpis:importar', 'kpis:resumen');
});
