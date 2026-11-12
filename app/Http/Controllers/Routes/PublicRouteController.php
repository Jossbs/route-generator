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
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * El gancho, tal como lo abre el lead desde WhatsApp.
 *
 * No pide sesión —quien la abre es el lead, no un asesor— y no toca el catálogo:
 * el documento se arma desde el snapshot, así que sigue diciendo lo mismo que
 * decía el día que se mandó, aunque el catálogo haya cambiado desde entonces.
 *
 * El acceso se conserva en la bitácora histórica. La visita con actividad,
 * las secciones y el avance se reciben aparte desde el navegador; una petición
 * de vista previa del chat no se presenta como una visita con actividad.
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

        return $documents->for($route)->with('readingEndpoint', URL::temporarySignedRoute(
            'routes.reading', now()->addDay(), [
                'token' => $token,
                'visit' => (string) Str::uuid(),
                'revision' => $route->revisions,
            ],
        ));
    }

    /**
     * La IP se guarda como hash, nunca en claro: sirve para distinguir aperturas y
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
