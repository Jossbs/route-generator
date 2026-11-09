<?php

declare(strict_types=1);

namespace App\Services\RouteComposition\Composers;

use App\Enums\RouteItemRole;
use App\Enums\RouteLayer;
use App\Models\Course;
use App\Models\Specialization;
use App\Services\RouteComposition\Contracts\RouteComposer;
use App\Services\RouteComposition\Data\CompositionPolicy;
use App\Services\RouteComposition\Data\CompositionRequest;
use App\Services\RouteComposition\Exceptions\CompositionException;
use App\Services\RouteComposition\Support\ComplementaryPicker;
use App\Services\RouteComposition\Support\CompositionCatalog;
use App\Services\RouteComposition\Support\CourseRanker;
use App\Services\RouteComposition\Support\DraftAssembler;
use App\Services\RouteComposition\Support\ExclusionGuard;
use App\Services\RouteComposition\Support\InitialRouteContinuity;
use App\Services\RouteComposition\Support\LeadSignals;
use App\Services\RouteComposition\Support\MonthPlanner;
use App\Services\RouteValidation\Data\RouteDraft;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Support\Collection;

/**
 * La cortesía posterior a la compra: el recorrido de doce meses sobre el catálogo
 * completo, más el inventario de todo lo que la membresía incluye.
 *
 * Son dos capas con reglas distintas. El itinerario recomienda —y por eso los tres
 * cursos de liderazgo no pueden convivir en él—; el inventario solo enumera, y ahí
 * los tres aparecen porque el miembro efectivamente los tiene.
 *
 * Los cursos son autogestivos: el documento propone una secuencia, no un
 * calendario. «Mes 1 sugerido», nunca «inicia el 3 de marzo».
 */
final class AnnualRouteComposer implements RouteComposer
{
    public function __construct(private readonly MonthPlanner $planner) {}

    public static function documentType(): string
    {
        return DocumentTypeSeeder::ANUAL;
    }

    public function compose(CompositionRequest $request): RouteDraft
    {
        if ($request->membership === null) {
            throw CompositionException::membershipRequired($request->documentType);
        }

        $policy = CompositionPolicy::for($request->documentType);
        $catalog = new CompositionCatalog($request->archetype);
        $guard = new ExclusionGuard($request->documentType->slug);
        $ranker = new CourseRanker($policy, $request->archetype);
        $assembler = new DraftAssembler;

        $pace = $request->pace();
        $priority = $this->specializationPriority($request, $catalog, $policy);
        $initial = (new InitialRouteContinuity)->forLead($request->lead);
        $initialRows = $initial?->snapshot['ruta']['cursos'] ?? [];
        $initialSlugs = [];

        foreach (is_array($initialRows) ? $initialRows : [] as $row) {
            if (is_array($row) && ($row['capa'] ?? null) === 'itinerario' && is_string($row['slug'] ?? null)) {
                $initialSlugs[] = $row['slug'];
            }
        }
        $universe = $catalog->resolvePackages($catalog->recommendable(), expandPackages: false);
        $included = $catalog->included()->keyBy('slug');
        $preferred = [];

        foreach ($initialSlugs as $slug) {
            $original = $included->get($slug);
            $equivalent = $universe->firstWhere('slug', $slug)
                ?? ($original === null ? null : $universe->firstWhere('content_unit_id', $original->content_unit_id));

            if ($equivalent !== null && ! in_array($equivalent->id, $preferred, true)) {
                $preferred[] = (int) $equivalent->id;
            }
        }

        $itinerary = $this->itineraryCourses(
            $catalog,
            $guard,
            $ranker,
            $request->itineraryCourseIds,
            $priority,
            $pace->capacity($policy->months()),
            $preferred,
        );

        // Con la lista del asesor, su orden es la secuencia y el motor solo la corta
        // en meses. Sin ella, el motor propone también el orden.
        $plan = $request->itineraryCourseIds === null && $preferred === []
            ? $this->planner->distribute($itinerary, $priority, $ranker, $policy->months(), $pace)
            : $this->planner->sequence(array_values($itinerary->all()), $policy->months(), $pace);
        $endMonths = $this->planner->endMonths();

        foreach ($plan as $month => $blocks) {
            foreach ($blocks as $specializationId => $courses) {
                $specialization = $catalog->specialization($specializationId);

                if ($specialization === null) {
                    continue;
                }

                $assembler->block(
                    specialization: $specialization,
                    courses: $courses,
                    role: $this->roleFor($specialization, $request->anchorSpecialization),
                    month: $month,
                    monthEnds: $endMonths,
                );
            }
        }

        $this->appendInventory($assembler, $catalog);

        return new RouteDraft(
            documentTypeSlug: $request->documentType->slug,
            items: $assembler->items(),
            anchorSpecializationId: $request->anchorSpecialization->id,
            headings: $assembler->headings(),
            copyText: [],
            membership: $request->membershipFields(),
            declaredCourseCount: $assembler->courseCount(),
            declaredContentUnitCount: $assembler->contentUnitCount(),
        );
    }

    /**
     * Lo que el itinerario puede recomendar: todo lo recomendable menos lo que
     * choca con algo ya elegido. El orden de la criba importa, porque de un grupo
     * de traslape sobrevive el primero, y `byWeight` pone al frente al más
     * representativo.
     *
     * Si el asesor ya pasó por la pantalla de los meses, lo que manda es la
     * secuencia —qué cursos y en qué orden— y sale de aquí en ese orden. No es un
     * preset: dónde termina cada mes, los prerrequisitos duros y el inventario de
     * la membresía siguen siendo del motor, que los recalcula sobre lo que quedó.
     * La criba de traslapes sigue corriendo por peso, así que de un grupo de
     * liderazgo sobrevive el más representativo aunque el asesor haya puesto otro
     * primero.
     *
     * Con el ritmo del miembro, la primera propuesta además tiene tope: se toma
     * especialización por especialización, en el orden de prioridad del lead, lo
     * que todavía cabe en su año. Un curso que no cabe se salta y se prueba el
     * siguiente, que puede ser más corto. La lista del asesor no se recorta aquí:
     * si se pasa, lo dice la pantalla y lo detiene la emisión.
     *
     * @param  list<int>|null  $only  Los cursos que el asesor dejó; null = sin poda.
     * @param  list<int>  $priority  Especializaciones en orden de prioridad para el lead.
     * @param  int|null  $capacity  Horas que caben en el año a su ritmo; null = sin tope.
     * @param  list<int>  $preferred  Cursos de la ruta inicial, incluidos sus refuerzos.
     * @return Collection<int, Course>
     */
    private function itineraryCourses(
        CompositionCatalog $catalog,
        ExclusionGuard $guard,
        CourseRanker $ranker,
        ?array $only,
        array $priority,
        ?int $capacity,
        array $preferred = [],
    ): Collection {
        // Condensado: en un recorrido de doce meses sobre todo el catálogo, el
        // paquete dice en un renglón lo que sus niveles dirían en tres, y evita que
        // una cadena de prerrequisitos duros consuma tres meses para 48 horas.
        $universe = $catalog->resolvePackages($catalog->recommendable(), expandPackages: false);

        if ($only !== null) {
            $universe = $universe->filter(
                fn (Course $course): bool => in_array((int) $course->id, $only, true),
            );
        }

        $candidates = $ranker->byWeight($universe);

        if ($only === null && $capacity !== null) {
            $rank = array_flip($priority);
            $weight = array_flip(array_map(fn (Course $c): int => $c->id, $candidates));

            usort($candidates, fn (Course $a, Course $b): int => [
                $rank[$a->specialization_id] ?? PHP_INT_MAX,
                $weight[$a->id],
            ] <=> [
                $rank[$b->specialization_id] ?? PHP_INT_MAX,
                $weight[$b->id],
            ]);
        }

        if ($only === null && $preferred !== []) {
            $order = array_flip($preferred);
            $remainingOrder = array_flip(array_map(fn (Course $c): int => $c->id, $candidates));
            usort($candidates, fn (Course $a, Course $b): int => [
                $order[$a->id] ?? PHP_INT_MAX, $remainingOrder[$a->id],
            ] <=> [
                $order[$b->id] ?? PHP_INT_MAX, $remainingOrder[$b->id],
            ]);
        }

        $chosen = [];
        $kept = [];
        $remaining = $only === null ? $capacity : null;

        foreach ($candidates as $course) {
            if (! $guard->admits($course->id, $chosen)) {
                continue;
            }

            $hours = $course->duration_hours ?? 0;

            // Si ni siquiera cabe la ruta inicial, la revisión debe mostrar el
            // bloqueo de capacidad. Quitar sus cursos en silencio rompería la
            // continuidad con lo que ya recibió el cliente.
            if ($remaining !== null && $hours > $remaining && ! in_array($course->id, $preferred, true)) {
                continue;
            }

            $kept[] = $course;
            $chosen[] = $course->id;

            if ($remaining !== null) {
                $remaining -= $hours;
            }
        }

        $kept = collect($kept)->keyBy('id');

        if ($only === null && $preferred !== []) {
            $first = $kept->only($preferred);
            $rest = $kept->except($preferred)->groupBy('specialization_id');
            $ordered = $first->values()->all();

            foreach ($priority as $specializationId) {
                $ordered = [...$ordered, ...$ranker->sequence($rest->get($specializationId, collect()))];
            }

            return collect($ordered)->keyBy('id');
        }

        return $only === null
            ? $kept
            : collect($only)
                ->unique()
                ->filter(fn (int $id): bool => $kept->has($id))
                ->mapWithKeys(fn (int $id): array => [$id => $kept->get($id)]);
    }

    /**
     * En qué orden se recorren las especializaciones, que es también el orden en
     * que se eligen cuando el año no alcanza para todo.
     *
     * El mes 1 arranca con la que el lead pidió, para que sienta continuidad con
     * la conversación que ya tuvo con su asesor. Después, las que su perfil pide
     * —industria, área y puesto, con el mismo criterio que elige las
     * complementarias del gancho— y al final el resto, por afinidad y orden de
     * presentación. Con pocas horas a la semana, este orden decide qué entra.
     *
     * @return list<int>
     */
    private function specializationPriority(
        CompositionRequest $request,
        CompositionCatalog $catalog,
        CompositionPolicy $policy,
    ): array {
        $anchor = $request->anchorSpecialization;

        $byProfile = array_map(
            fn (array $pick): int => $pick[0]->id,
            (new ComplementaryPicker)->for(
                LeadSignals::of($request->lead),
                $catalog,
                [$anchor->id],
                $catalog->specializations()->count(),
            ),
        );

        $rest = $catalog->specializations()
            ->reject(fn (Specialization $s): bool => $s->id === $anchor->id || in_array($s->id, $byProfile, true))
            ->sortBy(fn (Specialization $s): array => [-$catalog->affinityFor($s), $s->display_order])
            ->pluck('id')
            ->map(intval(...))
            ->values()
            ->all();

        return $policy->anchorOpensFirstMonth()
            ? [$anchor->id, ...$byProfile, ...$rest]
            : [...$byProfile, ...$rest, $anchor->id];
    }

    /**
     * La especialización que el lead pidió es el ancla del documento; las demás son
     * refuerzo. Ninguna es «complementaria» aquí: ese rol pertenece al gancho, donde
     * los cursos complementarios acompañan la ruta principal.
     *
     * Cadena de Suministros abre el mes 1 si es lo que el lead pidió, pero bajo el
     * rol de bloque, no de ancla: su ficha no admite ese papel ni siquiera aquí, y
     * el documento anual la presenta como una especialidad más con título propio.
     */
    private function roleFor(Specialization $specialization, Specialization $anchor): RouteItemRole
    {
        return $specialization->id === $anchor->id && $specialization->allowsRole('ancla')
            ? RouteItemRole::Ancla
            : RouteItemRole::Refuerzo;
    }

    /**
     * El inventario: todo lo que la membresía cubre, en el orden de presentación
     * del catálogo. Aquí sí entran los cursos que el itinerario no recomienda —los
     * traslapes descartados y los que no tienen duración publicada—, porque el
     * miembro los tiene y el documento no debe escondérselos.
     */
    private function appendInventory(DraftAssembler $assembler, CompositionCatalog $catalog): void
    {
        foreach ($catalog->specializations() as $specialization) {
            $courses = array_values($catalog->included()
                ->where('specialization_id', $specialization->id)
                ->sortBy('name')
                ->all());

            $assembler->block(
                specialization: $specialization,
                courses: $courses,
                role: RouteItemRole::Incluido,
                layer: RouteLayer::Inventario,
            );
        }
    }
}
