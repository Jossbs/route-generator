<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El mensaje escrito que sustituye al audio, decidido el 2026-09-23.
 *
 * Cuando el asesor no puede grabar, igual tiene que contestarle al lead: el
 * mensaje va pegado junto al PDF. Es copy de cara al lead como el guión, así que
 * vive en `copy_blocks` con alcance propio y pasa por el mismo escaneo de fraseo.
 *
 * Laravel escribe el `enum` de Postgres como una restricción CHECK; ampliarlo es
 * reescribirla.
 */
return new class extends Migration
{
    private const BEFORE = ['portada', 'intro_especializacion', 'cierre', 'frase_puente', 'guion_audio'];

    private const AFTER = [...self::BEFORE, 'mensaje_whatsapp'];

    public function up(): void
    {
        $this->allow(self::AFTER);
    }

    public function down(): void
    {
        DB::table('copy_blocks')->where('scope', 'mensaje_whatsapp')->delete();

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
