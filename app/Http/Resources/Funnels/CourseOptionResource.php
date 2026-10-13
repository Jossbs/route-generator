<?php

declare(strict_types=1);

namespace App\Http\Resources\Funnels;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un curso del catálogo, como opción del selector.
 *
 * Trae las dos banderas de análisis —traslape sospechado y duración en disputa—
 * porque son las que el validador convierte en advertencia. Verlas antes de
 * agregar el curso ahorra el viaje de agregarlo, leer el ámbar y quitarlo.
 *
 * Nunca trae `outcome_phrase`: las 64 frases de resultado siguen sin aprobarse y
 * el editor no es lugar para estrenarlas.
 *
 * @mixin Course
 */
class CourseOptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->name,
            'slug' => $this->slug,
            'especialidad_id' => $this->specialization_id,
            'unidad_contenido_id' => $this->content_unit_id,
            'nivel' => $this->level->value,
            'horas' => $this->duration_hours,
            'revisar_traslape' => (bool) $this->needs_overlap_review,
            'revisar_duracion' => (bool) $this->needs_duration_review,
        ];
    }
}
