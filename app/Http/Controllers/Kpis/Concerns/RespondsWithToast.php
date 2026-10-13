<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kpis\Concerns;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Regresar a la pantalla con el aviso de lo que pasó, como el resto del sistema.
 */
trait RespondsWithToast
{
    protected function backWithToast(string $message, string $type = 'success'): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back();
    }
}
