<?php

declare(strict_types=1);

namespace App\Services\RouteComposition\Support;

use App\Models\Specialization;
use Illuminate\Support\Str;

/**
 * Con qué se acompaña la ruta principal, y por qué.
 *
 * El orden de preferencia no es arbitrario. Primero la industria: a un lead de
 * automotriz le toca Core Tools, y eso ninguna otra señal lo sabe. Después el
 * área, que es el único dato tipificado de la captura —el asesor la eligió de una
 * lista, no la escribió— y por eso pesa más que lo que se infiere leyendo un
 * texto. Al final el puesto: quien ya manda gente recibe Soft Skills, y quien no,
 * Solución de Problemas, que es lo que va a usar el lunes en el piso.
 *
 * Sin área, el reparto es el de siempre: industria o Kaizen, y luego puesto.
 *
 * Vive aparte del compositor porque lo necesitan dos caminos que no se parecen:
 * el que compone el documento desde el catálogo y el que completa un embudo corto
 * hasta el mínimo de recomendados. Que los dos elijan igual es lo que hace que el
 * lead reciba el mismo criterio venga por donde venga.
 *
 * Cada candidata puede caerse —ser la propia ruta principal, no admitir el rol,
 * quedarse sin cursos recomendables— y por eso detrás va la afinidad al
 * arquetipo: un documento sin complementarias deja de mostrarle al lead lo que no
 * sabía que venía dentro, que es de lo que vive el gancho.
 */
final class ComplementaryPicker
{
    /**
     * Las especializaciones que acompañan, con la razón que el asesor va a
     * defender en la conversación.
     *
     * @param  list<int>  $excludeIds  Lo que ya está en el documento.
     * @return list<array{Specialization, string}>
     */
    public function for(LeadSignals $signals, CompositionCatalog $catalog, array $excludeIds, int $limit): array
    {
        $bySlug = [];

        foreach ($catalog->eligibleFor('complementaria', excludeIds: $excludeIds) as $specialization) {
            $bySlug[$specialization->slug] = $specialization;
        }

        $chosen = [];

        foreach ($this->candidates($signals) as [$slug, $justification]) {
            if (isset($bySlug[$slug])) {
                $chosen[] = [$bySlug[$slug], $justification];
                unset($bySlug[$slug]);
            }
        }

        // El respaldo por afinidad, para completar los lugares que se cayeron.
        foreach ($bySlug as $specialization) {
            if (count($chosen) >= $limit) {
                break;
            }

            $chosen[] = [$specialization, 'De las que mejor acompañan a lo que pidió.'];
        }

        return array_slice($chosen, 0, $limit);
    }

    /**
     * Los slugs en orden de preferencia, cada uno con su porqué.
     *
     * @return list<array{string, string}>
     */
    private function candidates(LeadSignals $signals): array
    {
        $candidates = [];

        if ($signals->isAutomotive()) {
            $candidates[] = ['coretools', 'Porque trabaja en automotriz.'];
        } elseif ($signals->preferredSpecializations() === []) {
            $candidates[] = ['kaizen', 'Es la base que sostiene todo lo demás.'];
        }

        foreach ($signals->preferredSpecializations() as $slug) {
            $candidates[] = [$slug, 'Por el área en la que trabaja: '.Str::lower((string) $signals->areaLabel()).'.'];
        }

        $candidates[] = $signals->leadsPeople()
            ? ['soft', $signals->hasContext()
                ? 'Por lo que te dijo: '.Str::lower($signals->context)
                : 'Por el puesto que tiene.']
            : ['problemas', 'Es lo que va a usar el lunes en el piso.'];

        // El respaldo de siempre, por si todo lo anterior se cayó.
        $candidates[] = ['kaizen', 'Es la base que sostiene todo lo demás.'];

        return $candidates;
    }
}
