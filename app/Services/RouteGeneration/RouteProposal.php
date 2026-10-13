<?php

declare(strict_types=1);

namespace App\Services\RouteGeneration;

use App\Enums\RouteItemRole;
use App\Enums\RouteLayer;
use App\Models\Archetype;
use App\Models\Course;
use App\Models\DocumentType;
use App\Models\FunnelRoute;
use App\Models\Lead;
use App\Models\Membership;
use App\Models\User;
use App\Services\Catalog\RouteTotals;
use App\Services\Documents\EditableCopy;
use App\Services\RouteComposition\ComposerRegistry;
use App\Services\RouteComposition\Data\CompositionRequest;
use App\Services\RouteComposition\Support\PresetDraftBuilder;
use App\Services\RouteComposition\Support\RecommendedTopUp;
use App\Services\RouteComposition\Support\StudyPace;
use App\Services\RouteGeneration\Data\LeadCapture;
use App\Services\RouteValidation\Data\RouteItemDraft;
use App\Services\RouteValidation\RouteValidator;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Support\Collection;

/**
 * Lo que el motor propone, antes de que exista nada.
 *
 * «Nadie ve nada todavía. Tú revisas antes de que salga.» El paso intermedio de
 * la carcasa no es un adorno: es lo que convierte al asesor en autor del
 * documento en vez de en operador de un botón. Aquí no se escribe una sola fila
 * —ni el lead, ni la ruta— porque una propuesta descartada no debería dejar
 * rastro en la bandeja.
 *
 * Devuelve los bloques agrupados por especialidad, cada uno con su justificación
 * interna, que es lo que el asesor lee para decidir si ese bloque se queda.
 */
final class RouteProposal
{
    public function __construct(
        private readonly ComposerRegistry $composers,
        private readonly PresetDraftBuilder $preset,
        private readonly RouteValidator $validator,
        private readonly FunnelRouteLocator $funnelRoutes,
        private readonly RecommendedTopUp $topUp,
        private readonly EditableCopy $editable,
    ) {}

    /**
     * @param  list<int>|null  $courseIds  Lo que el asesor dejó; null = lo que el motor propone.
     * @param  array<string, mixed>|null  $membership  Datos de membresía, si el documento los exige.
     * @return array<string, mixed>
     */
    public function for(
        LeadCapture $capture,
        User $advisor,
        DocumentType $documentType,
        ?array $courseIds = null,
        ?array $membership = null,
    ): array {
        $funnelRoute = $this->funnelRoutes->for($capture);
        $lead = $this->lead($capture, $advisor, $funnelRoute);
        $anchor = $funnelRoute->mainSpecialization;
        $esGancho = $documentType->slug === DocumentTypeSeeder::ENFOCADA;

        // El gancho se propone con los cursos del embudo: es la rama que el lead
        // va a ver como su ruta principal, y es lo que el asesor ajusta encima. El
        // anual sigue componiéndose desde el catálogo —son doce meses y un
        // inventario completo— con la especialidad del embudo abriendo el mes 1,
        // así que ahí lo que el asesor dejó poda el itinerario en vez de sustituirlo.
        $preset = $esGancho ? ($courseIds ?? $funnelRoute->orderedCourseIds()) : null;

        // Los cursos que la campaña curó. Son la ruta principal completa —todos,
        // vengan de la especialidad que vengan— y por eso viajan aparte: es lo que
        // le dice a la pantalla qué no se puede quitar, y al borrador qué renglones
        // no son una recomendación del motor.
        $curados = $esGancho ? $funnelRoute->orderedCourseIds() : [];

        // Los dos recomendados solo se proponen la primera vez, cuando la
        // lista es la del embudo tal cual. Si el asesor ya pasó por la pantalla y
        // quitó cursos, lo que manda de vuelta es su decisión: volver a sumarlos
        // aquí sería discutirle en silencio lo que acaba de decidir.
        $sumados = $esGancho && $courseIds === null
            ? $this->topUp->for($curados, $anchor, $lead, $documentType)
            : [];

        // La ruta principal entra completa aunque el asesor mande otra cosa: la
        // pantalla no deja quitarla, y una petición que la recorte llegó por otro
        // camino. Se antepone en el orden en que se curó.
        $preset = $preset === null
            ? null
            : [...$curados, ...array_diff([...$preset, ...array_keys($sumados)], $curados)];

        $draft = $preset === null
            ? $this->composers->for($documentType)->compose(new CompositionRequest(
                lead: $lead,
                documentType: $documentType,
                anchorSpecialization: $anchor,
                archetype: $lead->archetype,
                // También en memoria: el documento anual no se compone sin la
                // vigencia —ordena los doce meses— pero la membresía no se da de
                // alta hasta que el asesor acepta lo que ve.
                membership: $membership === null ? null : new Membership($membership),
                itineraryCourseIds: $courseIds,
            ))
            : $this->preset->build(
                documentType: $documentType,
                courseIds: $preset,
                anchorSpecializationId: $anchor?->id,
                copyText: $funnelRoute->copyText(),
                membership: $membership,
                presetName: $funnelRoute->funnel?->internal_name,
                justificationsByCourseId: $sumados,
                curatedCourseIds: $curados,
                waivers: $funnelRoute->ruleWaivers(),
            );

        $report = $this->validator->validate($draft);

        // Solo el itinerario: es la capa que el asesor revisa y ajusta. El
        // inventario de la membresía no se propone ni se poda —el miembro tiene
        // todo el catálogo lo lea o no— y listarlo aquí daría a entender que
        // quitarle un renglón se lo quita.
        $itinerary = $draft->itemsInLayer(RouteLayer::Itinerario);
        $courses = Course::query()
            ->whereIn('id', array_map(fn (RouteItemDraft $item): int => $item->courseId, $itinerary))
            ->get()
            ->keyBy('id');

        $totales = RouteTotals::for($courses->values())->toArray();

        return [
            'bloques' => $this->blocks($itinerary, $courses, $esGancho),
            'totales' => $totales,
            'ritmo' => $this->pace($membership, (int) ($totales['horas_netas'] ?? 0)),
            'bloqueos' => array_values(array_map(
                fn ($evaluation): array => [
                    'codigo' => $evaluation->ruleCode,
                    'mensaje' => $evaluation->message ?? $evaluation->ruleCode,
                    'arreglo' => $this->sequenceFix($evaluation->details),
                ],
                $report->blockingFailures()->all(),
            )),
            'curso_ids' => array_values(array_unique(array_map(
                fn (RouteItemDraft $item): int => $item->courseId,
                $itinerary,
            ))),
            // Los párrafos que el asesor puede ajustar, con el texto que el
            // documento imprimiría. Es el mismo cálculo que hace el documento.
            'parrafos' => $this->editable->paragraphs($documentType->slug, [
                'ocupacion' => $membership['occupation'] ?? $capture->occupation,
                'contexto' => $capture->context,
                'ancla' => $anchor?->name,
                'primer_curso' => $courses->get($itinerary[0]->courseId ?? 0)?->name,
            ]),
        ];
    }

    /**
     * El lead en memoria: existe para que el compositor pueda leer su ocupación y
     * lo que dijo, y desaparece si el asesor descarta la propuesta.
     */
    private function lead(LeadCapture $capture, User $advisor, FunnelRoute $funnelRoute): Lead
    {
        $lead = new Lead([
            'name' => $capture->name,
            'phone' => $capture->phone,
            'occupation' => $capture->occupation,
            'context' => $capture->context,
            ...$capture->area->toAttributes(),
            'source' => 'formulario',
            'requested_specialization_id' => $funnelRoute->main_specialization_id,
            'advisor_id' => $advisor->id,
            'status' => 'prospecto',
        ]);

        $lead->setRelation('advisor', $advisor);
        $lead->setRelation('archetype', Archetype::query()->where('slug', 'general')->first());
        $lead->setRelation('requestedSpecialization', $funnelRoute->mainSpecialization);

        return $lead;
    }

    /**
     * Cuánto del año del miembro ocupa lo que se ve, para que el asesor sepa si le
     * cabe otro curso antes de agregarlo. El límite lo hace cumplir
     * ANNUAL_FITS_PACE; esto solo lo dice en voz alta.
     *
     * @param  array<string, mixed>|null  $membership
     * @return array{horas_semana: int, capacidad: int, horas: int}|null
     */
    private function pace(?array $membership, int $hours): ?array
    {
        $weekly = $membership['weekly_hours'] ?? null;

        if ($weekly === null) {
            return null;
        }

        return [
            'horas_semana' => (int) $weekly,
            'capacidad' => (new StudyPace((int) $weekly))->capacity(12),
            'horas' => $hours,
        ];
    }

    /**
     * El arreglo de un curso puesto antes de lo que necesita: llevar el requisito
     * justo antes del curso que lo pide.
     *
     * Se entrega como movimientos y no como una lista ya corregida porque la
     * secuencia es del asesor: la pantalla aplica el movimiento sobre lo que él
     * dejó, sin tocar nada más. Una violación que no es de orden no trae arreglo.
     *
     * @param  array<string, mixed>  $details
     * @return list<array{mover: int, antes_de: int}>
     */
    private function sequenceFix(array $details): array
    {
        $moves = [];

        foreach ($details['violations'] ?? [] as $violation) {
            $move = match ($violation['kind'] ?? null) {
                'level_order' => [
                    'mover' => (int) $violation['expected_first_course_id'],
                    'antes_de' => (int) $violation['placed_first_course_id'],
                ],
                'prerequisite_order' => [
                    'mover' => (int) $violation['requires_course_id'],
                    'antes_de' => (int) $violation['course_id'],
                ],
                default => null,
            };

            if ($move !== null) {
                $moves[] = $move;
            }
        }

        return $moves;
    }

    /**
     * Los bloques tal como se leen en la pantalla: la ruta principal primero, cada
     * uno con el porqué que el asesor va a defender en la conversación.
     *
     * En el gancho, la ruta principal puede ocupar varios bloques —el embudo
     * mezcla especialidades a propósito— y todos van marcados como fijos. Lo que
     * el motor recomendó abre bloque aparte, y es lo único que la pantalla deja
     * podar. En los doce meses no hay nada fijo: los roles ancla y refuerzo solo
     * dicen qué especialidad pidió el lead, y el asesor quita, agrega y ordena
     * todo el itinerario.
     *
     * El mes viaja con el bloque porque en el documento anual es lo que ordena la
     * lectura. Dos especialidades pueden caer en el mismo mes y cada una conserva
     * su bloque: comparten mes, jamás encabezado.
     *
     * @param  list<RouteItemDraft>  $items
     * @param  Collection<int, Course>  $courses
     * @return list<array<string, mixed>>
     */
    private function blocks(array $items, Collection $courses, bool $esGancho): array
    {
        $bloques = [];
        $enLaEspecialidad = [];

        foreach ($items as $item) {
            $course = $courses->get($item->courseId);

            if ($course === null) {
                continue;
            }

            // Un curso del embudo y uno recomendado nunca comparten bloque aunque
            // sean de la misma especialidad: son las dos partes del documento y se
            // leen distinto. La especialidad conserva su título en las dos.
            $fijo = $esGancho && $item->role !== RouteItemRole::Complementaria;
            $clave = $item->month.':'.($fijo ? 'p' : 'r').':'.$item->specializationId;

            $bloques[$clave] ??= [
                'especialidad_id' => $item->specializationId,
                'especialidad' => $course->specialization?->name,
                'mes' => $item->month,
                'principal' => $fijo,
                'abre' => $item->role === RouteItemRole::Ancla,
                'justificacion' => $item->justification,
                // Lo que la especialidad trae completa en la membresía, para poder
                // nombrarla entera aunque solo se recomienden algunos cursos.
                'cursos_en_la_especialidad' => $enLaEspecialidad[$item->specializationId] ??= Course::query()
                    ->where('specialization_id', $item->specializationId)
                    ->where('is_active', true)
                    ->count(),
                'cursos' => [],
            ];

            $bloques[$clave]['cursos'][] = [
                'id' => (int) $course->id,
                'nombre' => $course->name,
                'horas' => $course->duration_hours,
                'orden' => $item->position,
                'fijo' => $fijo,
                'mes_fin' => $item->monthEnd,
            ];
        }

        return array_values($bloques);
    }
}
