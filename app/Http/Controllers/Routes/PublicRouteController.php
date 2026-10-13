<?php

declare(strict_types=1);

namespace App\Http\Controllers\Routes;

use App\Enums\RouteStatus;
use App\Enums\UsageEventType;
use App\Http\Controllers\Controller;
use App\Models\LearningRoute;
use App\Services\Documents\DocumentView;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * El gancho, tal como lo abre el lead desde WhatsApp.
 *
 * No pide sesión —quien la abre es el lead, no un asesor— y no toca el catálogo:
 * el documento se arma desde el snapshot, así que sigue diciendo lo mismo que
 * decía el día que se mandó, aunque el catálogo haya cambiado desde entonces.
 *
 * Cada apertura se asienta en la bitácora. Es la única señal de vuelta que hay:
 * el gancho se entrega por enlace y ahí termina la visibilidad.
 */
class PublicRouteController extends Controller
{
    public function show(Request $request, string $token, DocumentView $documents): View
    {
        $route = LearningRoute::query()
            ->where('access_token', $token)
            ->whereIn('status', [RouteStatus::Validated, RouteStatus::Rendering, RouteStatus::Rendered, RouteStatus::Failed])
            ->firstOrFail();

        $this->log($request, $route);

        return $documents->for($route);
    }

    /**
     * La IP se guarda cifrada, nunca en claro: sirve para distinguir aperturas y
     * para nada más.
     */
    private function log(Request $request, LearningRoute $route): void
    {
        $route->usageEvents()->create([
            'event' => UsageEventType::AbiertaPorLead,
            'ip_hash' => hash('sha256', (string) $request->ip()),
            'meta' => ['user_agent' => substr((string) $request->userAgent(), 0, 255)],
        ]);
    }
}
