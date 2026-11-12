<?php

declare(strict_types=1);

use App\Services\Documents\InitialRouteScriptCopy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (InitialRouteScriptCopy::slots() as $slot) {
            DB::table('copy_blocks')->upsert(
                [[
                    'slug' => $slot['slug'],
                    'scope' => $slot['scope'],
                    'locale' => 'es_MX',
                    'body' => $slot['body'],
                    'claims' => json_encode($slot['claims'], JSON_UNESCAPED_UNICODE),
                    'applies_when' => json_encode($slot['applies_when']),
                    'status' => $slot['status'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]],
                ['slug'],
                ['scope', 'locale', 'body', 'claims', 'applies_when', 'status', 'updated_at'],
            );
        }

        DB::table('copy_blocks')->whereIn('slug', InitialRouteScriptCopy::retiredSlugs())->delete();
    }

    public function down(): void
    {
        // El rollback de esquema conserva el texto aprobado y los snapshots.
    }
};
