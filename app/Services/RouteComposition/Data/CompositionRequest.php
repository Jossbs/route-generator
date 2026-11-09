<?php

declare(strict_types=1);

namespace App\Services\RouteComposition\Data;

use App\Models\Archetype;
use App\Models\DocumentType;
use App\Models\Lead;
use App\Models\Membership;
use App\Models\Specialization;
use App\Services\RouteComposition\Support\StudyPace;

/**
 * Lo que hace falta para proponer una ruta, ya resuelto a modelos.
 *
 * El compositor recibe esto y no vuelve a preguntar de dónde salió el lead: el
 * formulario de hoy y el webhook del CRM de mañana construyen el mismo objeto.
 */
final readonly class CompositionRequest
{
    /**
     * @param  list<int>|null  $itineraryCourseIds  Los cursos que el asesor dejó en
     *                                              el itinerario; null = los que el
     *                                              compositor elija. En el anual es
     *                                              también el orden de lectura: el
     *                                              motor solo decide dónde termina
     *                                              cada mes, y el inventario de la
     *                                              membresía no se toca.
     */
    public function __construct(
        public Lead $lead,
        public DocumentType $documentType,
        public Specialization $anchorSpecialization,
        public ?Archetype $archetype = null,
        public ?Membership $membership = null,
        public ?array $itineraryCourseIds = null,
    ) {}

    /**
     * El ritmo fijo de estudio para las nuevas membresías. Para documentos
     * históricos conserva el ritmo que ya fue acordado.
     */
    public function pace(): StudyPace
    {
        $hours = $this->membership?->weekly_hours;

        return new StudyPace((int) ($hours ?? StudyPace::DEFAULT_WEEKLY_HOURS));
    }

    /**
     * Los datos de membresía tal como los inspecciona ANNUAL_REQUIRES_MEMBERSHIP.
     * Se pasan como arreglo y no como modelo para que la regla siga siendo un
     * intérprete genérico de campos requeridos.
     *
     * @return array<string, mixed>|null
     */
    public function membershipFields(): ?array
    {
        if ($this->membership === null) {
            return null;
        }

        return [
            'full_name' => $this->membership->full_name,
            'occupation' => $this->membership->occupation,
            'plan' => $this->membership->planLabel(),
            'starts_on' => $this->membership->starts_on->toDateString(),
            'ends_on' => $this->membership->ends_on->toDateString(),
            // Va impreso («a 5 h/semana») y es lo que recortó los meses.
            'weekly_hours' => $this->membership->weekly_hours ?? StudyPace::DEFAULT_WEEKLY_HOURS,
        ];
    }
}
