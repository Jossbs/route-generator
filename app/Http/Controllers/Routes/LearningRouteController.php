<?php

declare(strict_types=1);

namespace App\Http\Controllers\Routes;

use App\Enums\LeadArea;
use App\Enums\MembershipPlan;
use App\Enums\RouteStatus;
use App\Enums\UsageEventType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Routes\Concerns\ProvidesCourseCatalog;
use App\Http\Requests\Routes\ProposeRouteRequest;
use App\Http\Requests\Routes\StoreLearningRouteRequest;
use App\Http\Requests\Routes\ValidateRouteSelectionRequest;
use App\Http\Resources\Routes\LearningRouteSummaryResource;
use App\Jobs\RenderRouteDocument;
use App\Models\DocumentType;
use App\Models\Funnel;
use App\Models\LearningRoute;
use App\Services\Documents\CopyResolver;
use App\Services\Documents\DocumentFiles;
use App\Services\Documents\EditableCopy;
use App\Services\Documents\RouteCompanions;
use App\Services\Leads\AreaNormalizer;
use App\Services\Leads\MexicanPhone;
use App\Services\RouteComposition\Exceptions\CompositionException;
use App\Services\RouteComposition\Support\StudyPace;
use App\Services\RouteGeneration\Data\LeadCapture;
use App\Services\RouteGeneration\Exceptions\RouteRejected;
use App\Services\RouteGeneration\RouteIssuer;
use App\Services\RouteGeneration\RouteProposal;
use App\Services\RouteValidation\ValidationMessages;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La ruta inicial: la captura, la propuesta y la entrega del enlace.
 *
 * El disparo es manual: el asesor decide cuándo generar, con el lead delante en
 * WhatsApp. Por eso la captura es corta y el validador no aparece por ningún lado
 * mientras se llena, solo si termina bloqueando.
 */
class LearningRouteController extends Controller
{
    use ProvidesCourseCatalog;

    /**
     * La única puerta de entrada: la captura del lead.
     *
     * No hay bandeja. El asesor no viene a consultar un historial —lo tiene en
     * WhatsApp, que es donde ocurre la conversación—; viene a generar el
     * documento de alguien que tiene enfrente. Un mismo formulario sirve a los
     * dos documentos porque comparten cuatro campos; el tipo decide si además se
     * piden la vigencia y el plan.
     *
     * Los parámetros por URL existen desde el primer día aunque el CRM no esté
     * integrado: el día que el CRM mande al asesor aquí con el lead en la mano,
     * no hay nada que cambiar.
     */
    public function create(Request $request): Response
    {
        // El área que venga por URL puede estar escrita de cualquier forma: quien
        // la manda es el CRM, no la pantalla. Se resuelve aquí para que el select
        // aparezca ya elegido cuando está en el catálogo, y el campo abierto
        // solo cuando de verdad es otra.
        $area = (new AreaNormalizer)->resolve($request->string('area')->toString());

        return $this->form(
            tipo: $request->string('tipo')->toString() === 'final' ? 'final' : 'inicial',
            precargado: [
                'nombre' => $request->string('nombre')->toString(),
                // La pantalla pide los diez dígitos; la lada ya va puesta. Lo que
                // CRM mande sin forma de número se muestra como vino para que el
                // asesor lo vea y lo corrija.
                'phone' => MexicanPhone::national($request->string('telefono')->toString())
                    ?? $request->string('telefono')->toString(),
                'occupation' => $request->string('ocupacion')->toString(),
                'context' => $request->string('contexto')->toString(),
                'area' => $area->area->value ?? ($area->other === null ? '' : AreaNormalizer::OTRO),
                'area_other' => $area->other ?? '',
                // El anual se cierra casi siempre en el plan anual: llega elegido y
                // el asesor lo cambia si no fue ese.
                'plan' => MembershipPlan::ExpertAnual->value,
                'plan_other' => '',
                'price' => '',
                'desde' => null,
                'hasta' => null,
                'weekly_hours' => StudyPace::DEFAULT_WEEKLY_HOURS,
                'external_id' => $request->string('external_id')->toString(),
                // Solo la final lo usa: llega desde el gancho, en la bandeja o la ficha.
                'lead_id' => $request->integer('lead') ?: null,
                'funnel_id' => Funnel::query()
                    ->where('slug', $request->string('embudo')->toString())
                    ->value('id'),
            ],
        );
    }

    /**
     * La misma captura, sobre una ruta que ya salió.
     *
     * Corregir es rehacer el documento encima del mismo renglón: el nombre que se
     * escribió mal, la ocupación que el lead corrigió después, los dos cursos que
     * el asesor recomendó de más. El asesor también puede corregir el embudo si
     * el lead preguntó por otra ruta.
     *
     * El tipo tampoco se elige: un gancho no se convierte en anual editándolo. El
     * anual se emite aparte, desde la ficha del gancho que lo originó.
     */
    public function edit(LearningRoute $route): Response
    {
        $route->load(['lead', 'documentType', 'membership', 'funnel']);

        $lead = $route->lead;
        $membresia = $route->membership;

        return $this->form(
            tipo: $route->documentType->requires_membership ? 'final' : 'inicial',
            precargado: [
                // El nombre impreso manda sobre el del lead cuando hay membresía:
                // es el que el documento anual lleva en la portada.
                'nombre' => $membresia->full_name ?? $lead->name,
                'phone' => MexicanPhone::national($lead->phone) ?? (string) $lead->phone,
                'occupation' => (string) ($membresia->occupation ?? $lead->occupation),
                'context' => (string) $lead->context,
                'area' => $lead->area->value ?? ($lead->area_other === null ? '' : AreaNormalizer::OTRO),
                'area_other' => (string) $lead->area_other,
                'plan' => $membresia?->plan->value ?? MembershipPlan::ExpertAnual->value,
                'plan_other' => (string) $membresia?->plan_other,
                'price' => $membresia?->price === null ? '' : (string) $membresia->price,
                'desde' => $membresia?->starts_on->toDateString(),
                'hasta' => $membresia?->ends_on->toDateString(),
                'weekly_hours' => StudyPace::DEFAULT_WEEKLY_HOURS,
                'external_id' => (string) $lead->external_id,
                'lead_id' => null,
                'funnel_id' => $route->funnel_id,
            ],
            editando: [
                'id' => (int) $route->id,
                'revisiones' => (int) $route->revisions,
                'embudo' => $route->funnel?->internal_name,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $precargado
     * @param  array<string, mixed>|null  $editando
     */
    private function form(string $tipo, array $precargado, ?array $editando = null): Response
    {
        return Inertia::render('rutas/generar', [
            'tipo' => $tipo,
            'embudos' => $this->funnels(),
            'areas' => LeadArea::options(),
            // Los planes publicados. El plan va impreso en la portada del anual, así
            // que se elige de una lista cerrada en vez de teclearse: con campo
            // abierto el mismo plan llegaba escrito de tres formas a tres leads.
            'planes' => MembershipPlan::options(),
            'precargado' => $precargado,
            'editando' => $editando,
        ]);
    }

    /**
     * El paso intermedio: lo que el motor propone, sin escribir nada.
     *
     * «Nadie ve nada todavía. Tú revisas antes de que salga.» Es lo que convierte
     * al asesor en autor del documento en vez de en operador de un botón.
     */
    public function propose(ProposeRouteRequest $request, RouteProposal $proposal): Response|RedirectResponse
    {
        $capture = LeadCapture::fromArray($request->validated());

        try {
            $propuesta = $proposal->for(
                $capture,
                $request->user(),
                DocumentType::query()->where('slug', DocumentTypeSeeder::ENFOCADA)->sole(),
            );
        } catch (CompositionException $e) {
            throw ValidationException::withMessages(['funnel_id' => $e->getMessage()]);
        }

        return Inertia::render('rutas/propuesta', [
            ...$propuesta,
            'captura' => $request->validated(),
            'catalogo' => $this->catalog(),
            'ajustes' => (object) [],
            'editando' => null,
        ]);
    }

    /**
     * El mismo paso intermedio, corrigiendo una ruta que ya salió.
     *
     * Lo que se propone no es una propuesta nueva: son los cursos que este
     * documento ya llevaba, para que el asesor vea lo que mandó y toque solo lo
     * que quiere cambiar. Volver a recomendar de cero aquí sería devolverle los
     * cursos que él ya había quitado.
     */
    public function repropose(
        ProposeRouteRequest $request,
        LearningRoute $route,
        RouteProposal $proposal,
    ): Response|RedirectResponse {
        $this->assertFocused($route);

        try {
            $propuesta = $proposal->for(
                LeadCapture::fromArray($request->validated()),
                $request->user(),
                $route->documentType,
                $this->courseIds($request) ?? array_values(array_diff(
                    $route->itineraryCourseIds(),
                    $route->funnelRoute?->orderedCourseIds() ?? [],
                )),
                copyOverrides: $this->adjustments($request, DocumentTypeSeeder::ENFOCADA) ?? ($route->snapshot['ajustes_copy'] ?? []),
            );
        } catch (CompositionException $e) {
            throw ValidationException::withMessages(['funnel_id' => $e->getMessage()]);
        }

        return Inertia::render('rutas/propuesta', [
            ...$propuesta,
            'captura' => $request->validated(),
            'catalogo' => $this->catalog(),
            // Lo que ya había ajustado a mano: corregir no lo devuelve al catálogo.
            'ajustes' => (object) ($this->adjustments($request, DocumentTypeSeeder::ENFOCADA) ?? ($route->snapshot['ajustes_copy'] ?? [])),
            'editando' => [
                'id' => (int) $route->id,
                'revisiones' => (int) $route->revisions,
                'embudo' => $route->funnel?->internal_name,
            ],
        ]);
    }

    public function validateSelection(ValidateRouteSelectionRequest $request, RouteProposal $proposal, ?LearningRoute $route = null): JsonResponse
    {
        if ($route !== null) {
            $this->assertFocused($route);
        }

        $capture = $request->validated();
        try {
            $result = $proposal->for(
                LeadCapture::fromArray($capture),
                $request->user(),
                $route->documentType ?? DocumentType::where('slug', DocumentTypeSeeder::ENFOCADA)->sole(),
                $this->courseIds($request),
                copyOverrides: $this->adjustments($request, DocumentTypeSeeder::ENFOCADA) ?? [],
            );
        } catch (CompositionException $e) {
            throw ValidationException::withMessages(['funnel_id' => $e->getMessage()]);
        }

        return response()->json([
            'totales' => $result['totales'],
            'bloqueos' => $result['bloqueos'],
            'advertencias' => $result['advertencias'],
        ]);
    }

    public function store(StoreLearningRouteRequest $request, RouteIssuer $issuer): RedirectResponse|JsonResponse
    {
        try {
            $result = $issuer->issueFocused(
                LeadCapture::fromArray($request->validated()),
                $request->user(),
                $this->courseIds($request),
                $this->adjustments($request, DocumentTypeSeeder::ENFOCADA) ?? [],
                requireValid: true,
            );
        } catch (RouteRejected $e) {
            throw ValidationException::withMessages(['course_ids' => $e->report->blockingFailures()
                ->map(fn ($evaluation): string => app(ValidationMessages::class)->message($evaluation))->all()]);
        } catch (CompositionException $e) {
            // Un embudo vacío no puede sembrar nada, y eso se sabe antes de
            // componer. El mensaje ya viene redactado para quien está capturando.
            throw ValidationException::withMessages(['funnel_id' => $e->getMessage()]);
        }

        // El formulario devuelve los conflictos al editor antes de guardar.
        if ($result->report->passes()) {
            RenderRouteDocument::dispatch($result->route->id, $result->route->snapshot_hash, $result->route->revisions);
        }

        // La ficha a la que se llega recién generada descarga el PDF sola, en
        // cuanto la cola lo termina. El asesor viene de decir «generar»; pedirle
        // además que encuentre un botón es pedirle un paso que ya dio.
        if ($request->expectsJson()) {
            $request->session()->flash('descargar', true);

            return response()->json(['url' => route('routes.show', $result->route)]);
        }

        return to_route('routes.show', $result->route)->with('descargar', true);
    }

    /**
     * La corrección, emitida sobre el mismo renglón.
     *
     * Pisa el snapshot anterior: la bandeja tiene un documento por ruta, no una
     * cadena de versiones. Lo que queda dicho es cuántas veces se rehizo, y eso el
     * renglón lo muestra —ver la migración del 2026-09-17—.
     */
    public function update(
        StoreLearningRouteRequest $request,
        LearningRoute $route,
        RouteIssuer $issuer,
    ): RedirectResponse|JsonResponse {
        $this->assertFocused($route);

        try {
            $result = $issuer->reissueFocused(
                $route,
                LeadCapture::fromArray($request->validated()),
                $request->user(),
                $this->courseIds($request),
                $this->adjustments($request, DocumentTypeSeeder::ENFOCADA),
                requireValid: true,
            );
        } catch (RouteRejected $e) {
            throw ValidationException::withMessages(['course_ids' => $e->report->blockingFailures()
                ->map(fn ($evaluation): string => app(ValidationMessages::class)->message($evaluation))->all()]);
        } catch (CompositionException $e) {
            throw ValidationException::withMessages(['funnel_id' => $e->getMessage()]);
        }

        if ($result->report->passes()) {
            RenderRouteDocument::dispatch($result->route->id, $result->route->snapshot_hash, $result->route->revisions);
        }

        // Se corrigió para volver a mandarlo: el archivo baja solo, igual que
        // cuando se emitió la primera vez.
        if ($request->expectsJson()) {
            $request->session()->flash('descargar', true);

            return response()->json(['url' => route('routes.show', $result->route)]);
        }

        return to_route('routes.show', $result->route)->with('descargar', true);
    }

    /**
     * Borrar una ruta.
     *
     * Es borrado suave y no definitivo: la ruta arrastra sus validaciones y su
     * bitácora, que son la prueba de que las restricciones de docs/ARQUITECTURA.md §4 se
     * evaluaron antes de mandarle algo a alguien, y esa prueba no es del asesor que
     * hoy quiere limpiar su bandeja. Lo que sí desaparece es todo lo que está de
     * cara al lead: el renglón sale de la bandeja, el enlace deja de abrir y el
     * archivo se va del disco.
     */
    public function destroy(LearningRoute $route, DocumentFiles $files): RedirectResponse
    {
        DB::transaction(function () use ($route, $files): void {
            $route = LearningRoute::query()->lockForUpdate()->findOrFail($route->id);
            $files->forget($route);
            $route->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'La ruta se borró.']);

        return to_route('routes.index');
    }

    /**
     * El gancho se corrige por aquí; el anual, por su propio controlador.
     *
     * No es celo de organización: los dos documentos piden datos distintos —el
     * anual exige la vigencia y el plan— y corregir uno con el formulario del otro
     * emitiría un documento al que le faltan campos que van impresos.
     */
    private function assertFocused(LearningRoute $route): void
    {
        abort_if($route->documentType->requires_membership, 404);
    }

    /**
     * La entrega: el enlace, el mensaje y lo que el documento todavía no dice.
     */
    public function show(Request $request, LearningRoute $route, CopyResolver $copy, RouteCompanions $companions, ValidationMessages $messages): Response
    {
        $route->load([
            'advisor',
            'lead.requestedSpecialization',
            'documentType',
            'anchorSpecialization',
            'funnel',
            'membership',
            'usageEvents',
            'validations',
        ]);

        $scripts = $companions->for($route, $route->lead);
        $issues = $messages->recorded($route);

        return Inertia::render('rutas/detalle', [
            'ruta' => (new LearningRouteSummaryResource($route))->resolve(),
            'lead' => [
                'id' => $route->lead->id,
                'nombre' => $route->lead->name,
                'telefono' => MexicanPhone::display($route->lead->phone),
                'email' => $route->lead->email,
                'ocupacion' => $route->lead->occupation,
                // El valor, no la etiqueta: es lo que la captura de la ruta final
                // vuelve a recibir por URL, y así el select aparece elegido en vez
                // de mandar al asesor a buscar otra vez su área.
                'area' => $route->lead->area->value ?? $route->lead->area_other,
                'contexto' => $route->lead->context,
                'estado' => $route->lead->status,
                'especialidad' => $route->lead->requestedSpecialization?->name,
                // Lo que hace falta para abrir la ruta final sin volver a
                // capturar al mismo lead: sin el embudo el asesor tendría que
                // adivinar cuál eligió, y sin el identificador del CRM el
                // miembro entraría por segunda vez a la base.
                'embudo' => $route->funnel?->slug,
                'external_id' => $route->lead->external_id,
            ],
            // Lo que se vendió. El documento no imprime el importe —docs/ARQUITECTURA.md §4.5—
            // así que esta ficha es el único sitio donde el asesor puede comprobar
            // lo que quedó asentado. Sin ella, el dato se captura y desaparece.
            'membresia' => $route->membership === null ? null : [
                'plan' => $route->membership->planLabel(),
                'importe' => $route->membership->price,
                'desde' => $route->membership->starts_on->toDateString(),
                'hasta' => $route->membership->ends_on->toDateString(),
            ],
            'totales' => $route->snapshot['ruta']['totales'] ?? null,
            'cursos' => $route->snapshot['ruta']['cursos'] ?? [],
            // Los documentos anteriores muestran el nuevo complemento usando
            // sus datos congelados, sin modificar el PDF ni el snapshot original.
            'guion' => $scripts['guion'],
            'mensaje' => $scripts['mensaje'],
            'rechazos' => $issues['bloqueos'],
            'advertencias' => $issues['advertencias'],
            'aperturas' => $route->usageEvents
                ->where('event', UsageEventType::AbiertaPorLead)
                ->map(fn ($event): array => ['fecha' => $event->created_at->toIso8601String()])
                ->values()
                ->all(),
            // Los huecos de copy no impiden entregar, pero dejan de ser sorpresa.
            'copyPendiente' => $copy->missingFor($route->documentType),
            // Mismo criterio con el botón de WhatsApp del cierre: sin número en el
            // perfil del asesor el documento sale sin él y se lee igual, pero
            // enterarse al abrir el PDF ya mandado es enterarse tarde.
            'sinWhatsApp' => $route->documentType->requires_tracking
                && blank($route->advisor?->whatsapp),
            'documentoUrl' => route('routes.document', $route),
            // El archivo es la entrega. Mientras la cola lo arma, la ficha se
            // refresca sola; si el render falla, queda la vista imprimible, que
            // no depende del contenedor de Chromium.
            'pdf' => [
                'listo' => $route->hasPdf(),
                'url' => route('routes.pdf', $route),
                'nombre' => $route->downloadName(),
            ],
            'autoDescarga' => $request->session()->get('descargar', false) === true,
            'puedeEmitirAnual' => $route->documentType->slug === DocumentTypeSeeder::ENFOCADA
                && $route->status !== RouteStatus::Rejected,
            'anualEmitido' => $route->lead->learningRoutes()
                ->whereHas('documentType', fn (Builder $q) => $q->where('slug', DocumentTypeSeeder::ANUAL))
                ->value('id'),
        ]);
    }

    /**
     * Lo que el asesor puede elegir: los embudos armados, con cuántos cursos
     * lleva cada uno.
     *
     * Solo aparecen los que tienen cursos. Un embudo vacío existe en el listado
     * interno —alguien lo está armando— pero ofrecerlo aquí sería ofrecer un
     * documento en blanco.
     *
     * @return list<array{value: int, label: string, cursos: int, especialidad: string|null}>
     */
    private function funnels(): array
    {
        return array_values(Funnel::query()
            ->selectable()
            ->with('primaryRoute.mainSpecialization')
            ->withCount('courseLinks as cursos')
            ->whereHas('courseLinks')
            ->orderBy('internal_name')
            ->get()
            ->map(fn (Funnel $funnel): array => [
                'value' => (int) $funnel->id,
                'label' => $funnel->internal_name,
                'cursos' => (int) $funnel->getAttribute('cursos'),
                'especialidad' => $funnel->primaryRoute?->mainSpecialization?->name,
            ])
            ->all());
    }

    /**
     * Los cursos que el asesor dejó en la propuesta. Null = lo que el motor
     * componga: una lista vacía no es una ruta sin cursos, es una petición que no
     * pasó por el paso intermedio.
     *
     * @return list<int>|null
     */
    private function courseIds(StoreLearningRouteRequest $request): ?array
    {
        /** @var list<int|string> $ids */
        $ids = $request->validated('course_ids') ?? [];

        return $ids === [] ? null : array_map(intval(...), $ids);
    }

    /**
     * Los párrafos que el asesor ajustó en la revisión, limpios. Null cuando la
     * petición no trae el campo, para que una corrección conserve los que ya
     * tenía el documento.
     *
     * @return array<string, string>|null
     */
    private function adjustments(Request $request, string $documentType): ?array
    {
        if (! $request->has('ajustes')) {
            return null;
        }

        return app(EditableCopy::class)->clean((array) $request->input('ajustes', []), $documentType);
    }
}
