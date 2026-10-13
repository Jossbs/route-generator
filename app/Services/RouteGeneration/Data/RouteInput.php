<?php

declare(strict_types=1);

namespace App\Services\RouteGeneration\Data;

use App\Models\Archetype;
use App\Models\DocumentType;
use App\Models\Funnel;
use App\Models\FunnelRoute;
use App\Models\Lead;
use App\Models\Membership;
use App\Models\Specialization;
use App\Services\RouteComposition\Data\CompositionRequest;
use InvalidArgumentException;

/**
 * Lo que entra al motor de rutas. Puede venir sembrado por un embudo o vacío.
 *
 * Las dos formas de armar una ruta desembocan aquí: el asesor llena el formulario
 * y el motor compone desde el catálogo, o el asesor elige un embudo que precarga
 * cursos que él todavía puede modificar. Un solo objeto de entrada, un solo motor,
 * un solo snapshot.
 *
 * El asesor decide siempre. El embudo precarga, no impone: por eso `courseIds`
 * viaja aparte de `funnelRoute` y no se lee de la ruta curada en el momento de
 * generar. Lo que se emite es lo que el asesor tenía en pantalla.
 */
final readonly class RouteInput
{
    /**
     * @param  list<int>  $courseIds  Vacío = el motor compone desde el catálogo.
     * @param  list<int>|null  $itineraryCourseIds  La poda del asesor sobre un documento
     *                                              que el motor sigue componiendo. No es
     *                                              un preset: restringe qué cursos entran
     *                                              al itinerario y deja el reparto en
     *                                              meses y el inventario donde estaban.
     * @param  array<int, int|null>  $monthsByCourseId
     * @param  array<int, string>  $justificationsByCourseId  El porqué de los cursos que no
     *                                                        vinieron del embudo, para que el snapshot
     *                                                        no diga que salieron de una campaña que no
     *                                                        los eligió.
     * @param  list<string>  $acknowledgedWarnings  Códigos de advertencia confirmados al publicar.
     * @param  array<string, string>  $copyOverrides  Los párrafos que el asesor ajustó a mano en la
     *                                                revisión, por su clave de copy. Se escanean
     *                                                como el resto del texto antes de emitir.
     */
    public function __construct(
        public Lead $lead,
        public DocumentType $documentType,
        public ?Specialization $anchorSpecialization = null,
        public ?Archetype $archetype = null,
        public ?Membership $membership = null,
        public ?Funnel $funnel = null,
        public ?FunnelRoute $funnelRoute = null,
        public array $courseIds = [],
        public ?array $itineraryCourseIds = null,
        public array $monthsByCourseId = [],
        public array $justificationsByCourseId = [],
        public ?int $durationMonths = null,
        public array $acknowledgedWarnings = [],
        public array $copyOverrides = [],
    ) {}

    /** El flujo que ya existía: el motor compone la ruta desde el catálogo. */
    public static function free(CompositionRequest $request): self
    {
        return new self(
            lead: $request->lead,
            documentType: $request->documentType,
            anchorSpecialization: $request->anchorSpecialization,
            archetype: $request->archetype,
            membership: $request->membership,
        );
    }

    /**
     * El flujo del embudo: los cursos llegan precargados desde la ruta curada.
     *
     * @param  list<int>|null  $courseIds  null = tal como los dejó Eje Cursos.
     * @param  array<int, string>  $justificationsByCourseId
     * @param  list<string>  $acknowledgedWarnings
     * @param  array<string, string>  $copyOverrides
     */
    public static function fromFunnel(
        Lead $lead,
        DocumentType $documentType,
        FunnelRoute $funnelRoute,
        ?array $courseIds = null,
        ?Membership $membership = null,
        array $acknowledgedWarnings = [],
        array $justificationsByCourseId = [],
        array $copyOverrides = [],
    ): self {
        $funnelRoute->loadMissing('items.course', 'funnel', 'archetype', 'mainSpecialization');

        return new self(
            lead: $lead,
            documentType: $documentType,
            anchorSpecialization: $funnelRoute->mainSpecialization,
            archetype: $funnelRoute->archetype,
            membership: $membership,
            funnel: $funnelRoute->funnel,
            funnelRoute: $funnelRoute,
            courseIds: $courseIds ?? $funnelRoute->orderedCourseIds(),
            monthsByCourseId: $funnelRoute->monthsByCourseId(),
            justificationsByCourseId: $justificationsByCourseId,
            durationMonths: $funnelRoute->duration_months,
            acknowledgedWarnings: $acknowledgedWarnings,
            copyOverrides: $copyOverrides,
        );
    }

    /** ¿Trae cursos ya elegidos, o hay que componerlos? */
    public function hasPreset(): bool
    {
        return $this->courseIds !== [];
    }

    /**
     * El texto de cara al lead que el validador escanea antes de renderizar.
     *
     * @return list<string>
     */
    public function copyText(): array
    {
        return $this->funnelRoute?->copyText() ?? [];
    }

    /**
     * Los datos de membresía que el documento imprime.
     *
     * Son cinco, no los de la tabla. `ANNUAL_REQUIRES_MEMBERSHIP` inspecciona los
     * cuatro que exige docs/ARQUITECTURA.md §4.6 y el plan se suma porque va impreso en la
     * portada: antes se capturaba, se guardaba y no llegaba nunca —la portada caía
     * siempre en su respaldo «Membresía anual»—, así que el asesor elegía un plan
     * que el lead no veía.
     *
     * **El precio no está y no debe estarlo.** Esto alimenta al snapshot, y el
     * snapshot alimenta al PDF: lo que no viaja no se puede imprimir por descuido
     * (docs/ARQUITECTURA.md §4.5). Lo que se cobró vive en `memberships`.
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
            'weekly_hours' => $this->membership->weekly_hours,
        ];
    }

    /**
     * La solicitud que reciben los compositores. Exige especialización ancla: sin
     * ella no hay desde dónde componer.
     */
    public function compositionRequest(): CompositionRequest
    {
        return $this->compositionRequestOrNull()
            ?? throw new InvalidArgumentException(
                'La composición desde catálogo necesita una especialización ancla.',
            );
    }

    private function compositionRequestOrNull(): ?CompositionRequest
    {
        if ($this->anchorSpecialization === null) {
            return null;
        }

        return new CompositionRequest(
            lead: $this->lead,
            documentType: $this->documentType,
            anchorSpecialization: $this->anchorSpecialization,
            archetype: $this->archetype,
            membership: $this->membership,
            itineraryCourseIds: $this->itineraryCourseIds,
        );
    }
}
