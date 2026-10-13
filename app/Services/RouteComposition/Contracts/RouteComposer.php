<?php

declare(strict_types=1);

namespace App\Services\RouteComposition\Contracts;

use App\Services\RouteComposition\Data\CompositionRequest;
use App\Services\RouteValidation\Data\RouteDraft;

/**
 * Propone una ruta. No la aprueba: eso le toca al validador, y el generador no
 * persiste nada que el validador no haya dejado pasar.
 */
interface RouteComposer
{
    /** El slug de `document_types` que este compositor sabe armar. */
    public static function documentType(): string;

    public function compose(CompositionRequest $request): RouteDraft;
}
