<?php

declare(strict_types=1);

namespace App\Services\Kpis;

use App\Enums\Kpis\MeasureUnit;

/**
 * Cómo se escribe un valor según su unidad: «12», «$40,000», «25 %».
 *
 * Vive del lado del servidor porque lo usan el tablero, el PDF y el resumen de
 * Telegram, y los tres tienen que decir lo mismo.
 */
final class ValueFormatter
{
    public static function format(?float $value, MeasureUnit $unit): string
    {
        if ($value === null) {
            return '—';
        }

        $decimales = abs($value - round($value)) < 0.005 ? 0 : ($unit === MeasureUnit::Porcentaje ? 1 : 2);
        $numero = number_format($value, $decimales, '.', ',');

        return match ($unit) {
            MeasureUnit::Dinero => ($value < 0 ? '-$' : '$').ltrim($numero, '-'),
            MeasureUnit::Porcentaje => $numero.' %',
            default => $numero,
        };
    }
}
