<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Services\Documents\CatalogDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * El catálogo de cursos en PDF, con el cierre al WhatsApp de quien lo descarga.
 */
class CatalogDocumentController extends Controller
{
    public function download(Request $request, CatalogDocument $catalogo): StreamedResponse
    {
        $ruta = $catalogo->pdf($request->user());

        return Storage::disk((string) config('documents.disk'))
            ->download($ruta, 'Catalogo de cursos DP SyS.pdf');
    }
}
