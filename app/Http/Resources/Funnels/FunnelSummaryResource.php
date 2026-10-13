<?php

declare(strict_types=1);

namespace App\Http\Resources\Funnels;

use App\Models\Funnel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * El renglón del listado interno: nombre, cuándo se armó y cuántos cursos lleva.
 *
 * El número de cursos es la única cifra que dice de un vistazo si el embudo está
 * terminado. Uno con cero cursos existe, pero no engancha a nadie: aparece en el
 * listado y no en el selector del asesor.
 *
 * @mixin Funnel
 */
class FunnelSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'nombre' => $this->internal_name,
            'slug' => $this->slug,
            'cursos' => (int) ($this->cursos ?? 0),
            'creado_en' => $this->created_at?->toIso8601String(),
            'autor' => $this->whenLoaded('creator', fn (): ?string => $this->creator?->name),
        ];
    }
}
