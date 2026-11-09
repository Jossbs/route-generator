<?php

declare(strict_types=1);

use App\Models\Lead;
use Illuminate\Support\Facades\DB;

it('conserva los leads y sus identificadores al actualizar el origen del CRM', function () {
    DB::statement('ALTER TABLE leads DROP CONSTRAINT leads_source_check');
    DB::statement("ALTER TABLE leads ADD CONSTRAINT leads_source_check CHECK (source::text IN ('formulario', 'kommo'))");
    $lead = Lead::create(['name' => 'Lead existente', 'source' => 'kommo', 'external_id' => 'CRM-42']);

    $migration = require database_path('migrations/2026_11_08_120000_use_generic_crm_source.php');
    $migration->up();
    $migration->up();

    expect($lead->fresh()->getAttributes())
        ->toMatchArray(['id' => $lead->id, 'source' => 'crm', 'external_id' => 'CRM-42', 'name' => 'Lead existente']);
});
