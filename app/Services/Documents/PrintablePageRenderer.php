<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\RouteStatus;
use App\Models\LearningRoute;

/**
 * El documento se sirve como página, no como archivo.
 *
 * La plantilla ya trae `@page { size: Letter }`, así que imprimir a PDF desde el
 * navegador da exactamente el documento de tres u ocho hojas; no hay nada que
 * generar por adelantado. Eso deja `pdf_path` nulo a propósito: no es un render
 * fallido, es que todavía no hay archivo que guardar.
 *
 * Lo que sí hace es cerrar el ciclo de estados, para que la bandeja pueda
 * distinguir una ruta lista de una que nunca llegó a serlo, y para que el día que
 * un renderer de Chromium ocupe este lugar no haya que cambiar quién marca qué.
 */
final class PrintablePageRenderer implements DocumentRenderer
{
    public function render(LearningRoute $route): void
    {
        $route->forceFill([
            'status' => RouteStatus::Rendered,
            'rendered_at' => now(),
        ])->save();
    }
}
