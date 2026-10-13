<?php

declare(strict_types=1);

namespace App\Enums\Kpis;

/**
 * Si un evento cuenta.
 *
 * Solo `confirmado` entra a los cálculos. `sugerido` es lo que una fuente
 * automática propone —hoy, las membresías del generador de rutas— y espera a que
 * el administrador lo confirme; así una venta nunca se cuenta dos veces por
 * haberse capturado a mano y además sugerido.
 */
enum SalesEventStatus: string
{
    case Sugerido = 'sugerido';
    case Confirmado = 'confirmado';
    case Descartado = 'descartado';

    public function label(): string
    {
        return match ($this) {
            self::Sugerido => 'Por confirmar',
            self::Confirmado => 'Confirmado',
            self::Descartado => 'Descartado',
        };
    }
}
