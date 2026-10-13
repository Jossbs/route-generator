<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La hoja «Quiénes somos» de la ruta inicial, decidida el 2026-09-24.
 *
 * Cifras, empresas, fotos y reseñas publicadas en academy.dpsys.com.mx. Es copy
 * de cara al lead, así que vive en `copy_blocks` con alcance propio y pasa por el
 * mismo escaneo de fraseo que el resto del documento: una reseña que dijera
 * «equivale a un Green Belt» se bloquearía igual que si la hubiera escrito el
 * sistema.
 *
 * Laravel escribe el `enum` de Postgres como una restricción CHECK; ampliarlo es
 * reescribirla.
 */
return new class extends Migration
{
    private const BEFORE = ['portada', 'intro_especializacion', 'cierre', 'frase_puente', 'guion_audio', 'mensaje_whatsapp'];

    private const AFTER = [...self::BEFORE, 'prueba_social'];

    public function up(): void
    {
        $this->allow(self::AFTER);
    }

    public function down(): void
    {
        DB::table('copy_blocks')->where('scope', 'prueba_social')->delete();

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
