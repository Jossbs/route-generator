<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El guión del audio, decidido el 2026-09-22.
 *
 * El audio acompaña a la ruta inicial: el asesor lo graba con su voz y lo manda
 * junto al PDF. Su texto es copy de catálogo por la misma razón que el del
 * documento —docs/ARQUITECTURA.md §3: si viviera en un componente, el validador no podría
 * escanearlo—, así que entra a `copy_blocks` con un alcance propio.
 *
 * Laravel escribe el `enum` de Postgres como una restricción CHECK; ampliarlo es
 * reescribirla.
 */
return new class extends Migration
{
    private const BEFORE = ['portada', 'intro_especializacion', 'cierre', 'frase_puente'];

    private const AFTER = [...self::BEFORE, 'guion_audio'];

    public function up(): void
    {
        $this->allow(self::AFTER);
    }

    public function down(): void
    {
        DB::table('copy_blocks')->where('scope', 'guion_audio')->delete();

        $this->allow(self::BEFORE);
    }

    /** @param  list<string>  $scopes */
    private function allow(array $scopes): void
    {
        $list = implode(', ', array_map(fn (string $scope): string => "'{$scope}'", $scopes));

        DB::statement('ALTER TABLE copy_blocks DROP CONSTRAINT IF EXISTS copy_blocks_scope_check');
        DB::statement("ALTER TABLE copy_blocks ADD CONSTRAINT copy_blocks_scope_check CHECK (scope::text IN ({$list}))");
    }
};
