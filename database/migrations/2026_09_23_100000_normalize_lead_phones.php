<?php

declare(strict_types=1);

use App\Services\Leads\MexicanPhone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los teléfonos que ya estaban guardados, escritos como se guardan desde hoy.
 *
 * Hasta ahora se guardaban como el asesor los tecleaba, y el mismo número vivía en
 * la base como «2321435677», «+52 2321435677» y «232 143 56 77»: tres llaves para
 * un lead, y ninguna reencontraba a las otras. Solo se tocan los que son un número
 * de diez dígitos; lo demás se queda como está para que alguien lo revise, no
 * desaparece. No se fusionan leads: juntar dos fichas es una decisión, no una
 * limpieza.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('leads')
            ->whereNotNull('phone')
            ->orderBy('id')
            ->each(function (object $lead): void {
                $canonico = MexicanPhone::normalize($lead->phone);

                if ($canonico !== null && $canonico !== $lead->phone) {
                    DB::table('leads')->where('id', $lead->id)->update(['phone' => $canonico]);
                }
            });
    }

    public function down(): void
    {
        // El formato anterior no era uno: no hay a qué volver.
    }
};
