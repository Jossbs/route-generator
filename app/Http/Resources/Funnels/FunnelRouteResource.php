<?php

declare(strict_types=1);

namespace App\Http\Resources\Funnels;

use App\Models\FunnelRoute;
use App\Models\FunnelRouteCourse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * La ruta curada tal como la edita quien arma la promoción.
 *
 * `cursos` viaja en el orden en que se presentará. La posición no se captura: se
 * deriva del arreglo, para que reordenar sea mover un renglón y no renumerar a
 * mano.
 *
 * @mixin FunnelRoute
 */
class FunnelRouteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->name,
            'posicion' => $this->position,
            'perfil_lead_id' => $this->archetype_id,
            'especialidad_principal_id' => $this->main_specialization_id,
            'duracion_meses' => $this->duration_months,
            'texto_intro' => $this->intro_text,
            'texto_cierre' => $this->closing_text,
            'cursos' => $this->items
                ->sortBy('position')
                ->values()
                ->map(fn (FunnelRouteCourse $item): array => [
                    'curso_id' => (int) $item->course_id,
                    'mes_sugerido' => $item->suggested_month,
                ])
                ->all(),
        ];
    }
}
