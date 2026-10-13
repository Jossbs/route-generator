<?php

declare(strict_types=1);

namespace App\Http\Resources\Routes;

use App\Enums\UsageEventType;
use App\Models\LearningRoute;
use App\Services\Leads\MexicanPhone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * La ficha de una ruta emitida, tal como la lee el asesor al entregarla.
 *
 * Lleva las aperturas del lead porque es la única señal de vuelta que este
 * módulo tiene: el gancho se entrega por enlace y ahí termina la visibilidad,
 * salvo por si lo abrió.
 *
 * @mixin LearningRoute
 */
class LearningRouteSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lead' => $this->lead->name,
            'telefono' => MexicanPhone::display($this->lead->phone),
            // El mismo nombre que la bandeja: el asesor los conoce como inicial y
            // final, y «Ruta enfocada» es lenguaje del catálogo.
            'documento' => $this->documentType->advisorLabel(),
            'documento_slug' => $this->documentType->slug,
            'especialidad' => $this->whenLoaded(
                'anchorSpecialization',
                fn (): ?string => $this->anchorSpecialization?->name,
            ),
            'estado' => $this->status->value,
            'estado_etiqueta' => $this->status->label(),
            'cursos' => $this->course_count,
            'programas' => $this->content_unit_count,
            'generada_en' => $this->created_at?->toIso8601String(),
            // Cuántas veces se rehízo el documento después de mandarlo. La ficha
            // lo dice porque el snapshot que se está viendo es el último, no el
            // que el lead abrió la primera vez.
            'revisiones' => $this->revisions,
            'corregida_en' => $this->revised_at?->toIso8601String(),
            'aperturas' => $this->usageEvents
                ->where('event', UsageEventType::AbiertaPorLead)
                ->count(),
            'enlace' => $this->access_token === null
                ? null
                : route('routes.public', ['token' => $this->access_token]),
        ];
    }
}
