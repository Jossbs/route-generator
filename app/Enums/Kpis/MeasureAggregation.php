<?php

declare(strict_types=1);

namespace App\Enums\Kpis;

/**
 * Cómo se resumen los eventos de una medida agregada.
 */
enum MeasureAggregation: string
{
    case Conteo = 'conteo';
    case Suma = 'suma';
    case Promedio = 'promedio';
    case Minimo = 'minimo';
    case Maximo = 'maximo';

    public function label(): string
    {
        return match ($this) {
            self::Conteo => 'Contar eventos',
            self::Suma => 'Sumar un campo',
            self::Promedio => 'Promediar un campo',
            self::Minimo => 'Mínimo de un campo',
            self::Maximo => 'Máximo de un campo',
        };
    }

    /**
     * Si el resultado de dos tramos se puede sumar.
     *
     * Decide cómo se reparte la meta: diez ventas en el mes son un tercio por
     * día, pero un ticket promedio de $4,000 no se divide entre treinta.
     */
    public function isSummable(): bool
    {
        return $this === self::Conteo || $this === self::Suma;
    }

    public function needsField(): bool
    {
        return $this !== self::Conteo;
    }

    /**
     * La función de SQL que la calcula.
     *
     * @return literal-string
     */
    public function sql(): string
    {
        return match ($this) {
            self::Conteo => 'count',
            self::Suma => 'sum',
            self::Promedio => 'avg',
            self::Minimo => 'min',
            self::Maximo => 'max',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $a): array => ['value' => $a->value, 'label' => $a->label()], self::cases());
    }
}
