<?php

declare(strict_types=1);

namespace App\Enums;

enum RouteItemRole: string
{
    case Ancla = 'ancla';
    case Complementaria = 'complementaria';
    case Refuerzo = 'refuerzo';
    case Incluido = 'incluido';

    /**
     * Los roles que constituyen una recomendación. LEADERSHIP_OVERLAP se evalúa
     * sobre estos y no sobre `incluido`: el documento anual puede listar los tres
     * cursos de liderazgo como incluidos y recomendar solo uno.
     */
    public function isRecommendation(): bool
    {
        return $this !== self::Incluido;
    }
}
