<?php

declare(strict_types=1);

namespace App\Http\Requests\Funnels;

use App\Models\Funnel;

/**
 * Editar un embudo es reescribir su nombre y su lista de cursos.
 *
 * El identificador no se toca: es la llave con la que el snapshot de cada
 * documento ya emitido se refiere a este embudo, y renombrarlo dejaría huérfanas
 * referencias que ya salieron. Cambiar el nombre visible no lo mueve.
 */
class UpdateFunnelRequest extends FunnelRequest
{
    public function authorize(): bool
    {
        $funnel = $this->route('funnel');

        assert($funnel instanceof Funnel);

        return $this->user()?->can('update', $funnel) ?? false;
    }
}
