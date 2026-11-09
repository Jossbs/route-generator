<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE leads DROP CONSTRAINT IF EXISTS leads_source_check');
        DB::table('leads')->where('source', 'kommo')->update(['source' => 'crm']);
        DB::statement("ALTER TABLE leads ADD CONSTRAINT leads_source_check CHECK (source::text IN ('formulario', 'crm'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE leads DROP CONSTRAINT IF EXISTS leads_source_check');
        DB::table('leads')->where('source', 'crm')->update(['source' => 'kommo']);
        DB::statement("ALTER TABLE leads ADD CONSTRAINT leads_source_check CHECK (source::text IN ('formulario', 'kommo'))");
    }
};
