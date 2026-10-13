<?php

declare(strict_types=1);

namespace App\Enums\Kpis;

/**
 * Cómo obtiene su valor una medida.
 *
 * `agregado` sale directo de los eventos: cuenta, suma o promedia un campo de un
 * tipo de evento, con filtros. `formula` combina otras dos medidas: así se arma
 * la conversión (ventas / leads) o una venta neta (ventas − cancelaciones) sin
 * escribir código.
 */
enum MeasureKind: string
{
    case Agregado = 'agregado';
    case Formula = 'formula';
}
