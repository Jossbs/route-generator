<?php

declare(strict_types=1);

namespace App\Services\RouteGeneration;

use App\Enums\RouteItemRole;
use App\Enums\RouteStatus;
use App\Enums\UsageEventType;
use App\Models\CatalogVersion;
use App\Models\DocumentType;
use App\Models\LearningRoute;
use App\Services\Documents\CopyResolver;
use App\Services\Documents\DocumentFiles;
use App\Services\RouteComposition\ComposerRegistry;
use App\Services\RouteComposition\Support\InitialRouteContinuity;
use App\Services\RouteComposition\Support\PresetDraftBuilder;
use App\Services\RouteGeneration\Data\GenerationResult;
use App\Services\RouteGeneration\Data\RouteInput;
use App\Services\RouteGeneration\Exceptions\RouteRejected;
use App\Services\RouteValidation\Data\RouteDraft;
use App\Services\RouteValidation\Data\ValidationReport;
use App\Services\RouteValidation\RouteValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * El flujo completo, en un solo lugar: el compositor propone, el validador aprueba
 * o rechaza, y solo entonces la ruta queda en condiciones de renderizarse.
 *
 * Es un servicio y no lógica de controlador a propósito: hoy lo invoca el
 * formulario del asesor, mañana un webhook del CRM, y ninguno de los dos tendrá
 * que reimplementar el orden de los pasos.
 *
 * Una ruta rechazada también se persiste. El asesor necesita ver por qué, y la
 * bitácora necesita poder demostrar después que la restricción se evaluó.
 *
 * El origen —formulario libre o embudo que precarga cursos— solo decide de dónde
 * salen los cursos del borrador. De ahí en adelante no hay bifurcación: mismo
 * validador, mismo payload, misma tabla, misma cola. El embudo es un preset que
 * siembra al motor, no un motor paralelo.
 */
final class RouteGenerator
{
    public function __construct(
        private readonly ComposerRegistry $composers,
        private readonly PresetDraftBuilder $preset,
        private readonly PayloadBuilder $payloads,
        private readonly RouteValidator $validator,
        private readonly CopyResolver $copy,
        private readonly DocumentFiles $files,
    ) {}

    public function generate(RouteInput $input, bool $requireValid = false): GenerationResult
    {
        return $this->run($input, null, $requireValid);
    }

    /**
     * Lo mismo, encima de una ruta que ya existe.
     *
     * Corregir un documento emitido es rehacerlo: el compositor vuelve a armar el
     * borrador, el validador vuelve a evaluarlo y lo que se asienta pisa el
     * snapshot anterior. Pisar es la decisión —un renglón por documento, no una
     * cadena de versiones— y por eso `revisions` cuenta las veces: el snapshot que
     * queda es el último, no el que vio el lead la primera vez, y la bandeja lo
     * dice en vez de callarlo.
     *
     * No pasa por `generate()` con otro nombre: el token, el padre y el contador
     * son de la ruta que ya existía, y una ruta nueva no tiene qué conservar.
     */
    public function regenerate(RouteInput $input, LearningRoute $route, bool $requireValid = false): GenerationResult
    {
        return $this->run($input, $route, $requireValid);
    }

    /** @param array<string, string> $copyOverrides */
    public function review(RouteDraft $draft, DocumentType $documentType, array $copyOverrides = []): ValidationReport
    {
        return $this->validator->validate($this->withCatalogCopy($draft, $documentType, $copyOverrides));
    }

    private function run(RouteInput $input, ?LearningRoute $existing, bool $requireValid): GenerationResult
    {
        $draft = $this->draftFor($input);
        $report = $this->validator->validate($draft);

        if ($requireValid && ! $report->passes()) {
            throw new RouteRejected($report);
        }

        $route = DB::transaction(function () use ($input, $draft, $report, $existing): LearningRoute {
            $route = $existing === null
                ? $this->persist($input, $draft, $report)
                : $this->rewrite($existing, $input, $draft, $report);

            $this->validator->record($route, $report);

            $route->usageEvents()->create([
                'advisor_id' => $input->lead->advisor_id,
                'event' => $report->passes() ? UsageEventType::Generada : UsageEventType::Rechazada,
                'meta' => $this->eventMeta($route, $report),
            ]);

            return $route;
        });

        return new GenerationResult($route, $report);
    }

    /**
     * Lo que queda escrito en la bitácora.
     *
     * La corrección se asienta como una generación más y no como un evento nuevo:
     * los cuatro eventos son los del Diccionario de Datos y agregar uno para esto
     * sería inventar vocabulario para algo que sí es generar el documento otra vez.
     * Lo que la distingue es `revision`, que dice cuál de todas fue.
     *
     * @return array<string, mixed>|null
     */
    private function eventMeta(LearningRoute $route, ValidationReport $report): ?array
    {
        $meta = $report->passes() ? [] : ['blocking' => $report->blockingMessages()];

        if ($route->revisions > 0) {
            $meta['revision'] = $route->revisions;
        }

        return $meta === [] ? null : $meta;
    }

    /**
     * El único punto donde el origen importa.
     *
     * Con preset, los cursos ya vienen elegidos —por Eje Cursos al curar el
     * embudo, y por el asesor al ajustarlos encima— y solo hay que darles forma de
     * borrador. Sin preset, el compositor los propone desde el catálogo. Si
     * aparece un `if` por origen más abajo, el diseño se desvió.
     */
    private function draftFor(RouteInput $input): RouteDraft
    {
        if (! $input->hasPreset()) {
            return $this->withCatalogCopy(
                $this->composers->for($input->documentType)->compose($input->compositionRequest()),
                $input->documentType,
                $input->copyOverrides,
            );
        }

        return $this->withCatalogCopy($this->preset->build(
            documentType: $input->documentType,
            courseIds: $input->courseIds,
            anchorSpecializationId: $input->anchorSpecialization?->id,
            monthsByCourseId: $input->monthsByCourseId,
            copyText: $input->copyText(),
            membership: $input->membershipFields(),
            presetName: $input->funnelRoute?->funnel?->internal_name,
            justificationsByCourseId: $input->justificationsByCourseId,
            curatedCourseIds: $input->funnelRoute?->orderedCourseIds(),
            waivers: $input->funnelRoute?->ruleWaivers(),
        ), $input->documentType, $input->copyOverrides);
    }

    /**
     * Suma al borrador el copy de catálogo que el documento va a imprimir.
     *
     * El copy vive en `copy_blocks` para que sea escaneable, pero hasta aquí el
     * validador solo veía el texto del embudo: los bloques de portada, intro y
     * cierre entraban a la plantilla ya pasado el validador, y una promesa de
     * equivalencia escrita ahí habría llegado impresa al lead.
     */
    /** @param array<string, string> $copyOverrides */
    private function withCatalogCopy(RouteDraft $draft, DocumentType $documentType, array $copyOverrides): RouteDraft
    {
        // Lo que el asesor ajustó a mano se escanea igual que el catálogo: un
        // párrafo editable no es una puerta para imprimir «equivale a».
        $bodies = [
            ...$this->copy->bodiesFor($documentType),
            ...array_values($copyOverrides),
            // El plan va impreso en la portada del anual, y con «Otro» es texto
            // que el asesor escribió a mano.
            ...array_filter([$draft->membership['plan'] ?? null], is_string(...)),
        ];

        if ($bodies === []) {
            return $draft;
        }

        return new RouteDraft(
            documentTypeSlug: $draft->documentTypeSlug,
            items: $draft->items,
            anchorSpecializationId: $draft->anchorSpecializationId,
            headings: $draft->headings,
            copyText: [...$draft->copyText, ...$bodies],
            membership: $draft->membership,
            declaredCourseCount: $draft->declaredCourseCount,
            declaredContentUnitCount: $draft->declaredContentUnitCount,
            waivers: $draft->waivers,
        );
    }

    private function persist(RouteInput $input, RouteDraft $draft, ValidationReport $report): LearningRoute
    {
        $route = LearningRoute::create([
            ...$this->attributes($input, $draft, $report),
            'parent_route_id' => $this->parentRouteId($input),
            // El token solo se emite si el documento se entrega por enlace y la
            // ruta pasó: un enlace a un documento rechazado no debe existir.
            'access_token' => $this->token($input, $report),
        ]);

        return $this->writeItems($route, $draft);
    }

    /**
     * La misma ruta, con el documento rehecho encima.
     *
     * Tres cosas no se vuelven a decidir. El token se conserva porque el lead
     * puede tener el enlace desde el primer envío y lo que tiene que abrir es el
     * documento corregido, no el de antes; si la ruta se emitió rechazada nunca
     * tuvo token y ahora se le emite uno. El padre tampoco se recalcula: qué
     * gancho originó este anual ya pasó y no cambia porque se corrija una fecha.
     * Y el archivo de antes se va del disco, porque describe un snapshot que ya
     * no existe.
     */
    private function rewrite(
        LearningRoute $route,
        RouteInput $input,
        RouteDraft $draft,
        ValidationReport $report,
    ): LearningRoute {
        $route = LearningRoute::query()->lockForUpdate()->findOrFail($route->id);

        $this->files->forget($route);

        $route->forceFill([
            ...$this->attributes($input, $draft, $report),
            'access_token' => $route->access_token ?? $this->token($input, $report),
            'revisions' => $route->revisions + 1,
            'revised_at' => now(),
        ])->save();

        $route->items()->delete();

        return $this->writeItems($route, $draft);
    }

    /**
     * Lo que la ruta asienta del borrador, se esté emitiendo o corrigiendo.
     *
     * @return array<string, mixed>
     */
    private function attributes(RouteInput $input, RouteDraft $draft, ValidationReport $report): array
    {
        $payload = $this->payloads->build($input, $draft, $report);

        return [
            'lead_id' => $input->lead->id,
            'membership_id' => $input->membership?->id,
            'document_type_id' => $input->documentType->id,
            'advisor_id' => $input->lead->advisor_id,
            'anchor_specialization_id' => $draft->anchorSpecializationId,
            ...$this->complementarySlots($draft),
            'archetype_id' => $input->archetype?->id,
            'funnel_id' => $input->funnel?->id,
            'funnel_route_id' => $input->funnelRoute?->id,
            'status' => $report->passes() ? RouteStatus::Validated : RouteStatus::Rejected,
            'catalog_version_id' => CatalogVersion::current()->id,
            'snapshot' => $payload,
            'snapshot_hash' => $this->payloads->hash($payload),
            'course_count' => $draft->declaredCourseCount ?? 0,
            'content_unit_count' => $draft->declaredContentUnitCount ?? 0,
        ];
    }

    private function token(RouteInput $input, ValidationReport $report): ?string
    {
        return $report->passes() && $input->documentType->requires_tracking
            ? Str::random(48)
            : null;
    }

    private function writeItems(LearningRoute $route, RouteDraft $draft): LearningRoute
    {
        $route->items()->createMany(array_map(
            fn ($item): array => $item->toArray(),
            $draft->items,
        ));

        return $route;
    }

    /**
     * Las dos especializaciones complementarias, tal como quedaron.
     *
     * El Diccionario de Datos las guarda como columnas y no solo como renglones
     * porque son una decisión y no un resultado: el motor propone dos, el asesor
     * puede cambiarlas, y lo que se asienta es lo que él dejó.
     *
     * @return array<string, int|null>
     */
    private function complementarySlots(RouteDraft $draft): array
    {
        $ids = array_values(array_unique(array_map(
            fn ($item): int => $item->specializationId,
            array_filter(
                $draft->items,
                fn ($item): bool => $item->role === RouteItemRole::Complementaria,
            ),
        )));

        return [
            'complementary_1_specialization_id' => $ids[0] ?? null,
            'complementary_2_specialization_id' => $ids[1] ?? null,
        ];
    }

    /**
     * El gancho que originó este documento anual, si lo hubo. Con los timestamps,
     * la conversión gancho → membresía queda como consulta y no necesita evento propio.
     */
    private function parentRouteId(RouteInput $input): ?int
    {
        if (! $input->documentType->requires_membership) {
            return null;
        }

        return (new InitialRouteContinuity)->forLead($input->lead)?->id;
    }
}
