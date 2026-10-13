<?php

declare(strict_types=1);

namespace App\Http\Controllers\Routes;

use App\Enums\RouteStatus;
use App\Http\Controllers\Controller;
use App\Jobs\RenderRouteDocument;
use App\Models\LearningRoute;
use App\Services\Documents\DocumentView;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * El documento: el archivo que se manda y la vista que se revisa antes.
 *
 * `download` es la entrega. El asesor adjunta ese PDF en el chat de Kommo, así
 * que el archivo llega con el nombre del lead en el nombre: un «documento.pdf»
 * en una conversación de WhatsApp no lo abre nadie.
 *
 * `show` es la misma plantilla servida desde el mismo snapshot, para revisarla en
 * pantalla. Una vista previa que se pareciera al documento sin ser el documento no
 * serviría para revisarlo antes de mandarlo, y además es el respaldo el día que el
 * contenedor de Chromium no esté: ahí se imprime desde el navegador.
 */
class RouteDocumentController extends Controller
{
    public function show(LearningRoute $route, DocumentView $documents): View
    {
        abort_if($route->snapshot === null || in_array($route->status, [RouteStatus::Draft, RouteStatus::Rejected], true), 404);

        return $documents->for($route, imprimible: true);
    }

    /** El archivo, con el nombre con el que va a viajar por el chat. */
    public function download(LearningRoute $route): StreamedResponse
    {
        $disco = Storage::disk((string) config('documents.disk'));

        abort_if(in_array($route->status, [RouteStatus::Draft, RouteStatus::Rejected], true)
            || $route->pdf_path === null || ! $disco->exists($route->pdf_path), 404);

        return $disco->download($route->pdf_path, $route->downloadName());
    }

    /**
     * Volver a pedir el archivo.
     *
     * Existe porque el render depende de un contenedor que puede no estar: si se
     * cayó, o si la ruta se generó antes de que hubiera archivos, el asesor tiene
     * que poder reintentar sin volver a capturar al lead ni emitir un documento
     * nuevo. El snapshot es el mismo, así que el PDF también: se pisa el anterior.
     *
     * Una ruta que el validador detuvo no se reintenta. No falló el render; falló
     * la ruta, y reintentarla entregaría lo que la restricción impidió entregar.
     */
    public function rerender(LearningRoute $route): RedirectResponse
    {
        $ready = DB::transaction(function () use ($route): ?LearningRoute {
            $route = LearningRoute::query()->lockForUpdate()->findOrFail($route->id);
            abort_if($route->snapshot === null || in_array($route->status, [RouteStatus::Draft, RouteStatus::Rejected], true), 404);

            if ($route->status === RouteStatus::Rendering) {
                return null;
            }

            $route->forceFill(['status' => RouteStatus::Validated])->save();

            return $route;
        });

        if ($ready !== null) {
            RenderRouteDocument::dispatch($ready->id, $ready->snapshot_hash, $ready->revisions);
        }

        return back();
    }
}
