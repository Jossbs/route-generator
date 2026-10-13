<?php

declare(strict_types=1);

namespace App\Http\Controllers\Funnels;

use App\Http\Controllers\Controller;
use App\Http\Requests\Funnels\StoreFunnelRouteRequest;
use App\Http\Requests\Funnels\UpdateFunnelRouteRequest;
use App\Models\Funnel;
use App\Models\FunnelRoute;
use App\Services\Funnels\FunnelRouteWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Las rutas curadas dentro de un embudo.
 *
 * Un embudo puede llevar varias, cada una apuntando a un perfil distinto: la
 * campaña de octubre con una ruta de Calidad, otra de Lean y otra de Six Sigma.
 * Guardar una ruta no publica nada; la puerta de publicación es aparte y evalúa
 * todas.
 */
class FunnelRouteController extends Controller
{
    public function store(StoreFunnelRouteRequest $request, Funnel $funnel): RedirectResponse
    {
        $funnel->routes()->create([
            'name' => $request->validated('name'),
            'position' => (int) $funnel->routes()->max('position') + 1,
            'duration_months' => 12,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ruta agregada.']);

        return back();
    }

    public function update(
        UpdateFunnelRouteRequest $request,
        Funnel $funnel,
        FunnelRoute $route,
        FunnelRouteWriter $writer,
    ): RedirectResponse {
        $this->assertBelongsTo($funnel, $route);

        $route->update($request->safe()->except('items'));

        $writer->sync($route, $request->validated('items'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ruta guardada.']);

        return back();
    }

    public function destroy(Funnel $funnel, FunnelRoute $route): RedirectResponse
    {
        Gate::authorize('update', $funnel);

        $this->assertBelongsTo($funnel, $route);

        $route->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ruta eliminada.']);

        return back();
    }

    /**
     * El embudo de la URL manda. Sin esto, un id de ruta de otro embudo se
     * editaría desde una pantalla que no le corresponde.
     */
    private function assertBelongsTo(Funnel $funnel, FunnelRoute $route): void
    {
        abort_unless((int) $route->funnel_id === (int) $funnel->id, 404);
    }
}
