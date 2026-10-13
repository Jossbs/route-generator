<?php

declare(strict_types=1);

namespace App\Http\Controllers\TrustHub;

use App\Enums\TrustEventType;
use App\Http\Controllers\Controller;
use App\Models\LearningRoute;
use App\Models\TrustEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Recibe lo que el centro de confianza reporta desde el navegador del lead.
 *
 * El sitio manda con `sendBeacon` y un cuerpo `text/plain`, que es lo que deja
 * cruzar de dominio sin preflight; por eso el JSON se lee del cuerpo crudo y no de
 * los campos de la petición. La respuesta siempre es vacía: nadie la lee.
 *
 * El token de la ruta inicial (`l`) es lo único que convierte una visita anónima
 * en la de un lead. Un token que no corresponde a nada no rechaza el evento —la
 * visita existió igual—, solo lo deja anónimo.
 */
class TrustEventController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($this->originAllowed($request), 403);

        $payload = json_decode($request->getContent(), true);

        $validator = Validator::make(is_array($payload) ? $payload : [], [
            'e' => ['required', Rule::enum(TrustEventType::class)],
            'v' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{8,36}$/'],
            't' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9:_-]+$/'],
            'a' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
            'l' => ['nullable', 'string', 'max:64'],
            'r' => ['nullable', 'string', 'max:255'],
            'utm' => ['nullable', 'array:source,medium,campaign,content'],
            'utm.*' => ['nullable', 'string', 'max:80'],
        ]);

        // Sin redirección de vuelta: quien llama es un beacon, no un formulario.
        abort_if($validator->fails(), 422);

        $data = $validator->validated();
        $utm = $data['utm'] ?? [];
        $utm = is_array($utm) ? Arr::whereNotNull($utm) : [];

        $route = isset($data['l'])
            ? LearningRoute::query()->where('access_token', $data['l'])->first()
            : null;

        TrustEvent::query()->create([
            'visit_id' => $data['v'],
            'event' => $data['e'],
            'target' => $data['t'] ?? null,
            'advisor_key' => $data['a'] ?? null,
            'advisor_id' => $route?->advisor_id,
            'route_id' => $route?->id,
            'lead_id' => $route?->lead_id,
            'ip_hash' => hash('sha256', (string) $request->ip()),
            'meta' => array_filter([
                'referrer' => $data['r'] ?? null,
                'utm' => $utm ?: null,
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]),
        ]);

        return response()->noContent();
    }

    /** Sin lista configurada acepta cualquiera; con lista, solo esos dominios. */
    private function originAllowed(Request $request): bool
    {
        $allowed = config('trust-hub.origins');

        return $allowed === [] || in_array($request->headers->get('Origin'), $allowed, true);
    }
}
