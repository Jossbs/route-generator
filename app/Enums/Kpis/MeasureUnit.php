<?php

declare(strict_types=1);

namespace App\Enums\Kpis;

/**
 * En qué se lee una medida.
 *
 * Una razón con unidad `porcentaje` se muestra y se le pone meta en por ciento
 * (25, no 0.25): quien fija la meta piensa en «25 % de conversión».
 */
enum MeasureUnit: string
{
    case Piezas = 'piezas';
    case Dinero = 'dinero';
    case Porcentaje = 'porcentaje';
    case Numero = 'numero';

    public function label(): string
    {
        return match ($this) {
            self::Piezas => 'Piezas',
            self::Dinero => 'Dinero (MXN)',
            self::Porcentaje => 'Porcentaje',
            self::Numero => 'Número',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $u): array => ['value' => $u->value, 'label' => $u->label()], self::cases());
    }
}
