<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\LearningRoute;
use Illuminate\Support\Facades\Storage;

/**
 * El archivo en el disco, cuando deja de ser el documento de nadie.
 *
 * Hay dos momentos en que el PDF guardado ya no representa a su ruta: cuando la
 * ruta se corrige —el snapshot cambió, así que el archivo de al lado promete algo
 * que el documento ya no dice— y cuando se borra. En los dos casos dejarlo ahí es
 * dejar que se pueda mandar: `download` solo mira `pdf_path`, y un asesor que baja
 * el archivo de ayer se lo adjunta al lead sin saber que corrigió para nada.
 *
 * Se olvida en dos pasos —el archivo y la columna— porque son dos verdades
 * distintas: una vive en el disco y la otra en la base, y la que decide si hay algo
 * que mandar es la segunda.
 */
final class DocumentFiles
{
    public function forget(LearningRoute $route): void
    {
        if ($route->pdf_path !== null) {
            Storage::disk((string) config('documents.disk'))->delete($route->pdf_path);
        }

        $route->forceFill(['pdf_path' => null, 'rendered_at' => null])->save();
    }
}
