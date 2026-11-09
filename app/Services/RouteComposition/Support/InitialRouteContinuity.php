<?php

declare(strict_types=1);

namespace App\Services\RouteComposition\Support;

use App\Enums\RouteStatus;
use App\Models\Lead;
use App\Models\LearningRoute;

/** La misma ruta inicial alimenta la propuesta anual y su vínculo de origen. */
final class InitialRouteContinuity
{
    public function forLead(Lead $lead): ?LearningRoute
    {
        $leadId = $lead->getAttribute('id');

        // La propuesta usa un lead en memoria; reconocerlo no debe guardarlo.
        if ($leadId === null && filled($lead->external_id)) {
            $leadId = Lead::query()->where('source', $lead->source)->where('external_id', $lead->external_id)->value('id');
        }
        if ($leadId === null && filled($lead->phone)) {
            $leadId = Lead::query()->where('source', $lead->source)->where('phone', $lead->phone)->value('id');
        }

        if ($leadId === null) {
            return null;
        }

        return LearningRoute::query()
            ->where('lead_id', $leadId)
            // Un fallo al convertir el archivo no invalida la ruta aprobada
            // que el cliente todavía puede consultar por su enlace.
            ->whereIn('status', [RouteStatus::Validated, RouteStatus::Rendering, RouteStatus::Rendered, RouteStatus::Failed])
            ->whereHas('documentType', fn ($q) => $q->where('requires_membership', false))
            ->latest('id')
            ->first();
    }
}
