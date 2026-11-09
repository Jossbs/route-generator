<?php

declare(strict_types=1);

namespace App\Services\RouteGeneration;

use App\Models\Archetype;
use App\Models\DocumentType;
use App\Models\FunnelRoute;
use App\Models\Lead;
use App\Models\LearningRoute;
use App\Models\Membership;
use App\Models\RouteItem;
use App\Models\User;
use App\Services\RouteComposition\Support\RecommendedTopUp;
use App\Services\RouteGeneration\Data\GenerationResult;
use App\Services\RouteGeneration\Data\LeadCapture;
use App\Services\RouteGeneration\Data\RouteInput;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Emite un documento para un lead.
 *
 * Entre la captura y el motor hay un paso que no es del motor: dar de alta al
 * lead, resolver su arquetipo y decidir con qué documento se le responde. Vive
 * aquí y no en el controlador por la razón de siempre —hoy lo llama un formulario,
 * mañana un webhook del CRM— y porque un lead a medio crear con una ruta que
 * falló no debe quedar en la base: las dos cosas van en la misma transacción.
 *
 * Los dos documentos tienen su propia captura y se emiten por separado. No son
 * variantes del mismo entregable: difieren en momento, en canal y en lo que piden.
 */
final class RouteIssuer
{
    public function __construct(
        private readonly RouteGenerator $generator,
        private readonly FunnelRouteLocator $funnelRoutes,
        private readonly RecommendedTopUp $topUp,
    ) {}

    /**
     * La ruta inicial: el gancho que se entrega por enlace, antes de la venta.
     *
     * @param  list<int>|null  $courseIds  Lo que el asesor dejó en la propuesta;
     *                                     null = lo que el motor componga.
     * @param  array<string, string>  $copyOverrides  Los párrafos que ajustó a mano.
     */
    public function issueFocused(
        LeadCapture $capture,
        User $advisor,
        ?array $courseIds = null,
        array $copyOverrides = [],
        bool $requireValid = false,
    ): GenerationResult {
        return DB::transaction(function () use ($capture, $advisor, $courseIds, $copyOverrides, $requireValid): GenerationResult {
            $funnelRoute = $this->funnelRoutes->for($capture);
            $lead = $this->lead($capture, $advisor, $funnelRoute);
            $documentType = $this->documentType(DocumentTypeSeeder::ENFOCADA);

            $curados = $funnelRoute->orderedCourseIds();

            // Emitir sin pasar por la pantalla es la vía del webhook: ahí nadie
            // revisó la propuesta, así que los recomendados los pone
            // el motor. Cuando el asesor sí revisó, su lista manda sobre ellos.
            $sumados = $courseIds === null
                ? $this->topUp->for($curados, $funnelRoute->mainSpecialization, $lead, $documentType)
                : [];

            // La ruta principal va completa, siempre y en su orden. El asesor poda
            // recomendaciones, no la campaña: lo que llegue recortado se completa
            // aquí en vez de emitir un documento que promete una rama a medias.
            $elegidos = $courseIds ?? array_keys($sumados);

            return $this->generator->generate(RouteInput::fromFunnel(
                lead: $lead,
                documentType: $documentType,
                funnelRoute: $funnelRoute,
                courseIds: [...$curados, ...array_values(array_diff($elegidos, $curados))],
                justificationsByCourseId: $sumados,
                copyOverrides: $copyOverrides,
            ), requireValid: $requireValid);
        });
    }

    /**
     * La ruta final: el recorrido de los doce meses, ya cerrada la membresía.
     *
     * Tiene su propia captura y no se dispara desde el gancho: la carcasa la trata
     * como documento aparte y los datos que pide son otros —nombre completo, que
     * va impreso, y la vigencia, que ordena los meses—. Si el lead ya pasó por
     * aquí se le reconoce por su teléfono, y el gancho que lo originó queda ligado
     * solo, por `parent_route_id`.
     *
     * @param  array<string, mixed>  $membershipData
     * @param  list<int>|null  $courseIds
     * @param  array<string, string>  $copyOverrides
     */
    public function issueAnnual(
        LeadCapture $capture,
        array $membershipData,
        User $advisor,
        ?array $courseIds = null,
        array $copyOverrides = [],
    ): GenerationResult {
        return DB::transaction(function () use ($capture, $membershipData, $advisor, $courseIds, $copyOverrides): GenerationResult {
            $funnelRoute = $this->funnelRoutes->for($capture);
            $lead = $this->lead($capture, $advisor, $funnelRoute);

            $membership = Membership::create([
                'lead_id' => $lead->id,
                ...$membershipData,
                'created_by' => $advisor->id,
            ]);

            $lead->update(['status' => 'miembro']);

            return $this->generator->generate($this->annualInput(
                $lead->refresh()->load('requestedSpecialization', 'archetype', 'advisor'),
                $funnelRoute,
                $courseIds,
                $membership,
                $copyOverrides,
            ));
        });
    }

    /**
     * La corrección de una ruta inicial ya emitida.
     *
     * Mismo lead y mismo renglón: además de corregir los datos, el asesor puede
     * elegir el embudo correcto si el que preguntó el lead se capturó mal.
     *
     * Los porqués de los cursos recomendados viajan de vuelta desde los renglones
     * que ya existían. Sin eso, corregir un nombre mal escrito dejaría la propuesta
     * sin la justificación que el asesor ya tenía escrita para defender cada curso.
     *
     * @param  list<int>|null  $courseIds  Lo que el asesor dejó en la propuesta;
     *                                     null = los que la ruta ya llevaba.
     * @param  array<string, string>|null  $copyOverrides  null = los que la ruta ya llevaba.
     */
    public function reissueFocused(
        LearningRoute $route,
        LeadCapture $capture,
        User $advisor,
        ?array $courseIds = null,
        ?array $copyOverrides = null,
        bool $requireValid = false,
    ): GenerationResult {
        return DB::transaction(function () use ($route, $capture, $advisor, $courseIds, $copyOverrides, $requireValid): GenerationResult {
            $funnelRoute = $this->funnelRoutes->for($capture);
            $lead = $this->correct($route, $capture, $advisor, $funnelRoute);

            $curados = $funnelRoute->orderedCourseIds();
            $elegidos = $courseIds ?? $route->itineraryCourseIds();

            // La ruta principal va completa también al corregir: el asesor poda
            // recomendaciones, nunca la campaña.
            return $this->generator->regenerate(
                RouteInput::fromFunnel(
                    lead: $lead,
                    documentType: $route->documentType,
                    funnelRoute: $funnelRoute,
                    courseIds: [...$curados, ...array_values(array_diff($elegidos, $curados))],
                    justificationsByCourseId: $this->justifications($route),
                    copyOverrides: $copyOverrides ?? $this->overrides($route),
                ),
                $route,
                requireValid: $requireValid,
            );
        });
    }

    /**
     * La corrección de una ruta final ya emitida.
     *
     * Aquí lo que suele estar mal son los datos de la membresía —el nombre
     * completo que va impreso, la vigencia que ordena los meses, el plan que se
     * cerró— y por eso se corrige la misma membresía en vez de dar de alta otra:
     * la venta fue una, y el expediente del miembro no debe contarla dos veces.
     *
     * @param  array<string, mixed>  $membershipData
     * @param  list<int>|null  $courseIds
     * @param  array<string, string>|null  $copyOverrides  null = los que la ruta ya llevaba.
     */
    public function reissueAnnual(
        LearningRoute $route,
        LeadCapture $capture,
        array $membershipData,
        User $advisor,
        ?array $courseIds = null,
        ?array $copyOverrides = null,
    ): GenerationResult {
        return DB::transaction(function () use ($route, $capture, $membershipData, $advisor, $courseIds, $copyOverrides): GenerationResult {
            $funnelRoute = $this->funnelRoutes->for($capture);
            $lead = $this->correct($route, $capture, $advisor, $funnelRoute);

            $membership = $route->membership;

            if ($membership === null) {
                $membership = Membership::create([
                    'lead_id' => $lead->id,
                    ...$membershipData,
                    'created_by' => $advisor->id,
                ]);
            } else {
                $membership->update($membershipData);
            }

            return $this->generator->regenerate(
                $this->annualInput(
                    $lead->load('requestedSpecialization', 'archetype', 'advisor'),
                    $funnelRoute,
                    $courseIds ?? $route->itineraryCourseIds(),
                    $membership,
                    $copyOverrides ?? $this->overrides($route),
                ),
                $route,
            );
        });
    }

    /**
     * El lead de una ruta que se corrige.
     *
     * Se actualiza el que la ruta ya tiene y no se vuelve a reencontrar por
     * teléfono: corregir un teléfono mal teclado no debe mudar la ruta al lead que
     * sí tenía ese número.
     *
     * El asesor sigue siendo el que la armó. La bandeja pregunta «quién lo armó»,
     * que es historia del documento, no de quién pasó después a corregir un acento;
     * solo se rellena cuando la ruta venía sin autor.
     */
    private function correct(
        LearningRoute $route,
        LeadCapture $capture,
        User $advisor,
        FunnelRoute $funnelRoute,
    ): Lead {
        $lead = $route->lead;

        $lead->update([
            'name' => $capture->name,
            'phone' => $capture->phone,
            'occupation' => $capture->occupation,
            'context' => $capture->context,
            ...$capture->area->toAttributes(),
            'requested_specialization_id' => $funnelRoute->main_specialization_id,
            'advisor_id' => $lead->advisor_id ?? $advisor->id,
            // Un identificador del CRM en blanco no borra el que ya había: la
            // captura puede llegar sin él y el par (origen, id) es la llave con la
            // que el CRM reencuentra a este mismo lead.
            ...($capture->externalId === null ? [] : ['external_id' => $capture->externalId]),
        ]);

        return $lead->load('requestedSpecialization', 'archetype', 'advisor');
    }

    /**
     * El porqué de cada curso, tal como quedó escrito al emitir.
     *
     * @return array<int, string>
     */
    private function justifications(LearningRoute $route): array
    {
        return $route->items()
            ->whereNotNull('justification')
            ->get()
            ->mapWithKeys(fn (RouteItem $item): array => [
                (int) $item->course_id => (string) $item->justification,
            ])
            ->all();
    }

    /**
     * Los párrafos que el asesor había ajustado, tal como quedaron en el snapshot.
     * Corregir un acento no debe devolver al catálogo lo que él ya había escrito.
     *
     * @return array<string, string>
     */
    private function overrides(LearningRoute $route): array
    {
        $ajustes = $route->snapshot['ajustes_copy'] ?? [];

        return is_array($ajustes) ? array_filter($ajustes, is_string(...)) : [];
    }

    /**
     * La entrada al motor para el documento anual.
     *
     * A diferencia del gancho, aquí el embudo no dicta la lista: el anual recorre
     * el catálogo completo en doce meses y además enumera todo lo que la membresía
     * incluye. Lo que el embudo aporta es por dónde empieza —su especialidad abre
     * el mes 1, para que el miembro sienta continuidad con la ruta que ya le
     * mandaron— y la trazabilidad de qué campaña lo trajo.
     *
     * Lo que el asesor ajustó en la pantalla de los meses entra como poda del
     * itinerario, nunca como preset. La diferencia no es de estilo: un preset
     * aplana el documento a una sola lista sin meses y sin inventario, que son
     * justamente las dos cosas que hacen anual al documento anual. El asesor
     * decide qué cursos entran; en qué mes cae cada uno lo sigue decidiendo el
     * motor, que es quien conoce los prerrequisitos duros.
     *
     * @param  list<int>|null  $courseIds
     * @param  array<string, string>  $copyOverrides
     */
    private function annualInput(
        Lead $lead,
        FunnelRoute $funnelRoute,
        ?array $courseIds,
        Membership $membership,
        array $copyOverrides = [],
    ): RouteInput {
        return new RouteInput(
            lead: $lead,
            documentType: $this->documentType(DocumentTypeSeeder::ANUAL),
            anchorSpecialization: $lead->requestedSpecialization,
            archetype: $lead->archetype,
            membership: $membership,
            funnel: $funnelRoute->funnel,
            funnelRoute: $funnelRoute,
            itineraryCourseIds: $courseIds,
            copyOverrides: $copyOverrides,
        );
    }

    /**
     * El lead, dado de alta o reencontrado.
     *
     * Se reencuentra por teléfono porque es lo que el asesor tiene delante en
     * WhatsApp: sin esto, el mismo lead al que se le manda un gancho en octubre y
     * su ruta final en noviembre aparecería dos veces y su conversión no se podría
     * seguir.
     *
     * Sin teléfono no hay con qué reencontrar, y en el gancho es opcional: ahí el
     * asesor puede estar armando la ruta antes de pedirlo. En ese caso se da de
     * alta un lead nuevo en vez de colgarse del primero que también lo tenga
     * vacío, que juntaría a dos personas distintas en una sola ficha. La ruta
     * final sí lo exige, porque es donde el reencuentro importa: es la que cierra
     * la conversión gancho → membresía.
     */
    private function lead(LeadCapture $capture, User $advisor, FunnelRoute $funnelRoute): Lead
    {
        $attributes = [
            'name' => $capture->name,
            'occupation' => $capture->occupation,
            'context' => $capture->context,
            // Ya viene resuelta: una categoría del catálogo, o el área nueva
            // estandarizada. Ver App\Services\Leads\AreaNormalizer.
            ...$capture->area->toAttributes(),
            // La especialidad por la que preguntó ya no se captura: es la que abre
            // el embudo que el asesor eligió, derivada de sus cursos.
            'requested_specialization_id' => $funnelRoute->main_specialization_id,
            'archetype_id' => Archetype::query()->where('slug', 'general')->value('id'),
            'advisor_id' => $advisor->id,
        ];

        $lead = match (true) {
            // Lo dice el asesor, que llegó desde el gancho: pesa más que cualquier
            // llave que haya que adivinar.
            $capture->leadId !== null => tap(Lead::query()->findOrFail($capture->leadId))
                ->update([...$attributes, 'phone' => $capture->phone]),
            $capture->externalId !== null => Lead::updateOrCreate(
                ['source' => 'crm', 'external_id' => $capture->externalId],
                [...$attributes, 'phone' => $capture->phone],
            ),
            $capture->phone !== null => Lead::updateOrCreate(
                ['source' => 'formulario', 'phone' => $capture->phone],
                $attributes,
            ),
            default => Lead::create([...$attributes, 'source' => 'formulario', 'phone' => null]),
        };

        return $lead->load('requestedSpecialization', 'archetype', 'advisor');
    }

    private function documentType(string $slug): DocumentType
    {
        return DocumentType::query()->where('slug', $slug)->sole();
    }
}
