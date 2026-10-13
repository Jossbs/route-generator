<?php

declare(strict_types=1);

namespace App\Http\Controllers\Funnels;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Funnels\Concerns\ProvidesEditorProps;
use App\Http\Requests\Funnels\StoreFunnelRequest;
use App\Http\Requests\Funnels\UpdateFunnelRequest;
use App\Http\Resources\Funnels\FunnelSummaryResource;
use App\Models\Funnel;
use App\Services\Funnels\FunnelWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El CRUD de embudos.
 *
 * Un embudo es un nombre y una lista de cursos: el cúmulo de una rama con el que
 * se engancha al lead. Todo lo que no sea eso —derivar el identificador, decidir
 * qué especialidad abre la lista, escribir las tres tablas— vive en
 * `FunnelWriter`, porque son condiciones sobre el contenido y no sobre la
 * petición HTTP.
 */
class FunnelController extends Controller
{
    use ProvidesEditorProps;

    /**
     * El listado interno.
     *
     * Ordena por creación descendente: con la rotación actual de campañas, lo
     * último armado es casi siempre lo que se busca.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Funnel::class);

        $filters = [
            'q' => trim($request->string('q')->toString()),
            'especialidad' => $request->string('especialidad')->toString(),
        ];

        $funnels = Funnel::query()
            ->with('creator')
            ->withCount('courseLinks as cursos')
            ->when($filters['q'] !== '', fn (Builder $q) => $q->where(
                fn (Builder $inner) => $inner
                    ->where('internal_name', 'ilike', "%{$filters['q']}%")
                    ->orWhere('slug', 'ilike', "%{$filters['q']}%"),
            ))
            ->when(
                ctype_digit($filters['especialidad']),
                fn (Builder $q) => $q->whereHas(
                    'routes.items.course',
                    fn (Builder $inner) => $inner->where('specialization_id', (int) $filters['especialidad']),
                ),
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('embudos/index', [
            'embudos' => FunnelSummaryResource::collection($funnels),
            'filtros' => $filters,
            'especialidades' => $this->specializations(),
            'puedeCrear' => $request->user()?->can('create', Funnel::class) ?? false,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Funnel::class);

        return Inertia::render('embudos/nuevo', $this->editorProps($request));
    }

    public function store(StoreFunnelRequest $request, FunnelWriter $writer): RedirectResponse
    {
        $funnel = $writer->create(
            $request->validated('name'),
            $request->courseIds(),
            $request->user(),
            $request->waivedRuleCodes(),
        );

        // Sin cursos no llega al selector, y decirle que sí lo haría mentir: el
        // listado interno la muestra igual, que es donde se sigue armando.
        Inertia::flash('toast', ['type' => 'success', 'message' => $funnel->courseLinks()->exists()
            ? 'Ruta creada. Ya aparece en el selector del asesor.'
            : 'Ruta creada. Le faltan los cursos para aparecer en el selector del asesor.']);

        return to_route('funnels.show', $funnel);
    }

    /** La lectura: qué lleva este embudo y qué dice el validador de esa lista. */
    public function show(Request $request, Funnel $funnel): Response
    {
        Gate::authorize('view', $funnel);

        $funnel->load('creator', 'primaryRoute.items.course.specialization');

        return Inertia::render('embudos/detalle', [
            ...$this->editorProps($request, $funnel),
            'embudo' => $this->funnelPayload($funnel),
            'validacion' => $this->funnelValidation($funnel),
        ]);
    }

    /**
     * El armador.
     *
     * Una sola pantalla para el nombre y la lista, con el panel del validador
     * enfrente mientras se eligen los cursos: es el elemento central de la vista y
     * no un paso posterior.
     */
    public function edit(Request $request, Funnel $funnel): Response
    {
        Gate::authorize('update', $funnel);

        $funnel->load('creator', 'primaryRoute.items');

        return Inertia::render('embudos/editar', [
            ...$this->editorProps($request, $funnel),
            'embudo' => $this->funnelPayload($funnel),
            'validacion' => $this->funnelValidation($funnel),
        ]);
    }

    public function update(UpdateFunnelRequest $request, Funnel $funnel, FunnelWriter $writer): RedirectResponse
    {
        $writer->update(
            $funnel,
            $request->validated('name'),
            $request->courseIds(),
            $request->user(),
            $request->waivedRuleCodes(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => $funnel->courseLinks()->exists()
            ? 'Cambios guardados.'
            : 'Cambios guardados. Sin cursos, la ruta no aparece en el selector del asesor.']);

        return to_route('funnels.show', $funnel);
    }

    /**
     * Borrarlo no toca nada de lo ya emitido: cada documento vive de su propio
     * snapshot, congelado el día que salió.
     */
    public function destroy(Request $request, Funnel $funnel): RedirectResponse
    {
        Gate::authorize('delete', $funnel);

        $funnel->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ruta eliminada.']);

        return to_route('funnels.index');
    }
}
