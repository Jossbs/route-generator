<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dispensar un hallazgo desde el embudo, decidido el 2026-09-22.
 *
 * `catalog_rules.waivable` dice qué reglas se pueden ignorar. Es dato y no código
 * por la misma razón que todo lo demás del motor: que una regla nueva sea
 * dispensable o no se decide al insertarla. Las ocho restricciones de docs/ARQUITECTURA.md §4
 * nacen en falso y así se quedan.
 *
 * `funnels.waived_rules` guarda qué ignoró cada embudo, quién y cuándo:
 * `[{code, by, at}]`. Va en JSONB porque son escalares de auditoría que se leen
 * con el embudo, no una relación que se consulte por su cuenta.
 */
return new class extends Migration
{
    /** Las ocho de docs/ARQUITECTURA.md §4: nunca se dispensan. */
    private const CRITICAL = [
        'SUPPLY_CHAIN_ROLE',
        'MODULE_DOUBLE_COUNT',
        'LEADERSHIP_OVERLAP',
        'CORE_TOOLS_PARTIAL',
        'NO_CERT_EQUIVALENCE',
        'ANNUAL_REQUIRES_MEMBERSHIP',
        'LEVEL_SEQUENCE',
        'NO_HEADING_MERGE',
    ];

    public function up(): void
    {
        Schema::table('catalog_rules', function (Blueprint $table): void {
            $table->boolean('waivable')->default(false)->after('severity');
        });

        Schema::table('funnels', function (Blueprint $table): void {
            $table->jsonb('waived_rules')->default('[]');
        });

        DB::table('catalog_rules')->whereNotIn('code', self::CRITICAL)->update(['waivable' => true]);
    }

    public function down(): void
    {
        Schema::table('funnels', function (Blueprint $table): void {
            $table->dropColumn('waived_rules');
        });

        Schema::table('catalog_rules', function (Blueprint $table): void {
            $table->dropColumn('waivable');
        });
    }
};
