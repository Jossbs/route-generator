<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\LearningRoute;

/**
 * Cómo se materializa un documento ya validado.
 *
 * Hay dos medios y una sola plantilla. El gancho se abre desde WhatsApp, casi
 * siempre en un teléfono, y se sirve como página responsiva; el anual se descarga
 * y se envía, y su medio natural es el impreso. Ninguno de los dos se arma desde
 * el catálogo: los dos se arman desde el snapshot, que es lo que hace que el
 * documento que el lead recibió en septiembre siga diciendo lo mismo en enero.
 *
 * La interfaz existe desde ahora porque el render en Chromium headless —VPS con
 * Browsershot o un contenedor de Gotenberg— es una decisión de infraestructura sin
 * tomar. Cuando se tome, se escribe otra implementación y no se toca ni la
 * plantilla, ni el payload, ni la cola.
 */
interface DocumentRenderer
{
    public function render(LearningRoute $route): void;
}
