<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Archetype;
use App\Models\CopyBlock;
use App\Models\Tag;
use Illuminate\Database\Seeder;

/**
 * v1 siembra un solo arquetipo, marcado como placeholder, con peso alto sobre el
 * clúster de empleabilidad: el puente de persuasión más fuerte del catálogo.
 *
 * Las afinidades por especialización quedan vacías a propósito. Llegan con las
 * ocho hojas del Excel; asignarles pesos ahora sería inventarlas.
 *
 * Esta tabla es también la que el módulo de embudos usa como perfil de lead: el
 * asesor elige el arquetipo de una lista y `match_rules` queda vacía, porque la
 * detección automática sigue fuera del alcance de la v1.
 *
 * Los perfiles reales los define DP SyS. No fabricarlos: sin ellos el campo queda
 * nulo, el selector muestra «Sin perfil» y el módulo funciona igual.
 */
class ArchetypeSeeder extends Seeder
{
    public function run(): void
    {
        $archetype = Archetype::updateOrCreate(
            ['slug' => 'general'],
            [
                'name' => 'General',
                'motivation' => 'Avance profesional. Marcador de posición hasta que lleguen los 8 arquetipos del Excel.',
                'bridge_phrase_block_id' => CopyBlock::where('slug', 'frase-puente.general')->value('id'),
                'is_placeholder' => true,
            ],
        );

        $empleabilidad = Tag::where('type', 'cluster')->where('slug', 'empleabilidad')->firstOrFail();

        $archetype->tagAffinities()->syncWithoutDetaching([
            $empleabilidad->id => ['weight' => 0.900],
        ]);
    }
}
