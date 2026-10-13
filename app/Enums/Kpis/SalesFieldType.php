<?php

declare(strict_types=1);

namespace App\Enums\Kpis;

/**
 * Los tipos de dato que puede llevar un campo de un tipo de evento.
 *
 * Son pocos a propósito: cada uno tiene que saber validarse en la captura y
 * leerse en el cálculo. Solo `numero` y `dinero` se pueden sumar o promediar;
 * `opcion`, `texto`, `si_no` y `fecha` sirven para filtrar.
 */
enum SalesFieldType: string
{
    case Numero = 'numero';
    case Dinero = 'dinero';
    case Texto = 'texto';
    case Opcion = 'opcion';
    case SiNo = 'si_no';
    case Fecha = 'fecha';

    public function label(): string
    {
        return match ($this) {
            self::Numero => 'Número',
            self::Dinero => 'Dinero',
            self::Texto => 'Texto',
            self::Opcion => 'Opción de una lista',
            self::SiNo => 'Sí / No',
            self::Fecha => 'Fecha',
        };
    }

    public function isNumeric(): bool
    {
        return $this === self::Numero || $this === self::Dinero;
    }

    /** La regla de validación del valor capturado. */
    public function rule(): string
    {
        return match ($this) {
            self::Numero, self::Dinero => 'numeric',
            self::Texto, self::Opcion => 'string',
            self::SiNo => 'boolean',
            self::Fecha => 'date',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $tipo): array => ['value' => $tipo->value, 'label' => $tipo->label()], self::cases());
    }
}
