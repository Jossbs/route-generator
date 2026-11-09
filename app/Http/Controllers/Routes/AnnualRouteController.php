<?php

declare(strict_types=1);

namespace App\Http\Controllers\Routes;

use App\Enums\MembershipPlan;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Routes\Concerns\ProvidesCourseCatalog;
use App\Http\Requests\Routes\StoreAnnualRouteRequest;
use App\Jobs\RenderRouteDocument;
use App\Models\DocumentType;
use App\Models\LearningRoute;
use App\Models\Membership;
use App\Services\Documents\EditableCopy;
use App\Services\RouteComposition\Exceptions\CompositionException;
use App\Services\RouteGeneration\Data\LeadCapture;
use App\Services\RouteGeneration\RouteIssuer;
use App\Services\RouteGeneration\RouteProposal;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La ruta final: el documento que se descarga, ya cerrada la membresía.
 *
 * Documento aparte, no variante del gancho: difiere en momento, en canal y en
 * contenido. Tiene su propia captura —con la vigencia, que va impresa y ordena
 * los doce meses— y su propio paso intermedio, que son los meses.
 */
class AnnualRouteController extends Controller
{
    use ProvidesCourseCatalog;

    /**
     * El paso intermedio de la ruta final: los doce meses, antes de generar nada.
     */
    public function propose(StoreAnnualRouteRequest $request, RouteProposal $proposal): Response|RedirectResponse
    {
        $datos = $request->validated();

        try {
            $propuesta = $proposal->for(
                $this->capture($datos),
                $request->user(),
                DocumentType::query()->where('slug', DocumentTypeSeeder::ANUAL)->sole(),
                // La primera vez no viene nada y el motor propone el orden. Después,
                // cada ajuste del asesor vuelve por aquí con su secuencia, para que
                // los meses se recorten sobre lo que acaba de dejar.
                isset($datos['course_ids'])
                    ? array_values(array_map(intval(...), $datos['course_ids']))
                    : null,
                $this->membershipAttributes($datos),
            );
        } catch (CompositionException $e) {
            return back()->withInput()->withErrors(['funnel_id' => $e->getMessage()]);
        }

        return Inertia::render('rutas/meses', [
            ...$propuesta,
            'captura' => $datos,
            'catalogo' => $this->catalog(),
            // Cada ajuste de la secuencia vuelve por aquí; los párrafos ajustados
            // viajan con él para no perderse a medio camino.
            'ajustes' => (object) ($datos['ajustes'] ?? []),
            'editando' => null,
        ]);
    }

    /**
     * Los mismos doce meses, corrigiendo un documento que ya se entregó.
     *
     * Lo que se vuelve a mostrar son los cursos que este documento ya llevaba, en
     * su orden. Dónde termina cada mes sí se rehace: las horas y los
     * prerrequisitos duros son del motor, no de la pantalla.
     */
    public function repropose(
        StoreAnnualRouteRequest $request,
        LearningRoute $route,
        RouteProposal $proposal,
    ): Response|RedirectResponse {
        $this->assertAnnual($route);

        $datos = $request->validated();

        try {
            $propuesta = $proposal->for(
                $this->capture($datos),
                $request->user(),
                $route->documentType,
                isset($datos['course_ids'])
                    ? array_values(array_map(intval(...), $datos['course_ids']))
                    : $route->itineraryCourseIds(),
                $this->membershipAttributes($datos),
            );
        } catch (CompositionException $e) {
            return back()->withInput()->withErrors(['funnel_id' => $e->getMessage()]);
        }

        return Inertia::render('rutas/meses', [
            ...$propuesta,
            'captura' => $datos,
            'catalogo' => $this->catalog(),
            'ajustes' => (object) ($datos['ajustes'] ?? $route->snapshot['ajustes_copy'] ?? []),
            'editando' => [
                'id' => (int) $route->id,
                'revisiones' => (int) $route->revisions,
                'embudo' => $route->funnel?->internal_name,
            ],
        ]);
    }

    public function store(StoreAnnualRouteRequest $request, RouteIssuer $issuer): RedirectResponse
    {
        $datos = $request->validated();

        try {
            $result = $issuer->issueAnnual(
                $this->capture($datos),
                $this->membershipAttributes($datos),
                $request->user(),
                isset($datos['course_ids'])
                    ? array_values(array_map(intval(...), $datos['course_ids']))
                    : null,
                $this->adjustments($request) ?? [],
            );
        } catch (CompositionException $e) {
            return back()->withInput()->withErrors(['funnel_id' => $e->getMessage()]);
        }

        if ($result->report->passes()) {
            RenderRouteDocument::dispatch($result->route->id, $result->route->snapshot_hash, $result->route->revisions);
        }

        // Igual que el gancho: el archivo baja solo en cuanto la cola lo termina.
        return to_route('routes.show', $result->route)->with('descargar', true);
    }

    /**
     * La corrección de la ruta final, sobre el mismo renglón y la misma membresía.
     *
     * Aquí la corrección es más que cosmética: la vigencia va impresa y ordena los
     * doce meses, así que cambiar una fecha rehace el documento entero. Por eso
     * pasa por el mismo validador —`ANNUAL_REQUIRES_MEMBERSHIP` incluido— en vez de
     * parchar el snapshot.
     */
    public function update(
        StoreAnnualRouteRequest $request,
        LearningRoute $route,
        RouteIssuer $issuer,
    ): RedirectResponse {
        $this->assertAnnual($route);

        $datos = $request->validated();

        try {
            $result = $issuer->reissueAnnual(
                $route,
                $this->capture($datos),
                $this->membershipAttributes($datos, $route->membership),
                $request->user(),
                isset($datos['course_ids'])
                    ? array_values(array_map(intval(...), $datos['course_ids']))
                    : null,
                $this->adjustments($request),
            );
        } catch (CompositionException $e) {
            return back()->withInput()->withErrors(['funnel_id' => $e->getMessage()]);
        }

        if ($result->report->passes()) {
            RenderRouteDocument::dispatch($result->route->id, $result->route->snapshot_hash, $result->route->revisions);
        }

        return to_route('routes.show', $result->route)->with('descargar', true);
    }

    /**
     * El anual se corrige por aquí; el gancho, por el suyo. Corregir uno con el
     * formulario del otro emitiría un documento sin campos que van impresos.
     */
    private function assertAnnual(LearningRoute $route): void
    {
        abort_unless($route->documentType->requires_membership, 404);
    }

    /**
     * El nombre completo del documento anual es también el nombre del lead: va
     * impreso en la portada y es el mismo con el que se le reconoce en la bandeja.
     *
     * @param  array<string, mixed>  $datos
     */
    private function capture(array $datos): LeadCapture
    {
        return LeadCapture::fromArray([...$datos, 'name' => $datos['full_name']]);
    }

    /**
     * La membresía, una sola vez.
     *
     * Los dos pasos la arman igual a propósito. Antes el de revisión la componía
     * sin el plan y el de emisión sí lo mandaba, así que el asesor revisaba un
     * documento que no era exactamente el que se iba a emitir; y es justo el paso
     * que existe para que revise antes de que salga.
     *
     * Es la forma de los atributos del modelo, no la de los campos que el documento
     * imprime: el importe entra aquí y se asienta en `memberships`, pero
     * `RouteInput::membershipFields()` no lo deja pasar al snapshot, que es lo que
     * alimenta al PDF (docs/ARQUITECTURA.md §4.5).
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function membershipAttributes(array $datos, ?Membership $membresia = null): array
    {
        $plan = MembershipPlan::from($datos['plan']);

        return [
            'full_name' => $datos['full_name'],
            'occupation' => $datos['occupation'],
            'plan' => $plan,
            'plan_other' => $plan === MembershipPlan::Otro ? $datos['plan_other'] : null,
            'price' => $membresia?->price,
            'starts_on' => $datos['starts_on'],
            'ends_on' => $datos['ends_on'],
            'weekly_hours' => (int) $datos['weekly_hours'],
        ];
    }

    /**
     * Los párrafos que el asesor ajustó en la revisión, limpios. Null cuando la
     * petición no trae el campo, para que una corrección conserve los que ya
     * tenía el documento.
     *
     * @return array<string, string>|null
     */
    private function adjustments(Request $request): ?array
    {
        if (! $request->has('ajustes')) {
            return null;
        }

        return app(EditableCopy::class)->clean((array) $request->input('ajustes', []), DocumentTypeSeeder::ANUAL);
    }
}
