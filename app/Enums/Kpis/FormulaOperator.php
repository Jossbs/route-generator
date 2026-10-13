<?php

declare(strict_types=1);

namespace App\Enums\Kpis;

/**
 * Cómo se combinan las dos medidas de una fórmula.
 *
 * `razon` no se suma entre personas ni entre días: la conversión del equipo no es
 * la suma de las conversiones, se recalcula dividiendo los totales. Por eso el
 * evaluador combina numerador y denominador ya agrupados y nunca promedia razones.
 */
enum FormulaOperator: string
{
    case Razon = 'razon';
    case Diferencia = 'diferencia';
    case Suma = 'suma';

    public function label(): string
    {
        return match ($this) {
            self::Razon => 'A ÷ B',
            self::Diferencia => 'A − B',
            self::Suma => 'A + B',
        };
    }

    public function apply(?float $a, ?float $b): ?float
    {
        return match ($this) {
            self::Razon => $a === null || $b === null || $b == 0.0 ? null : $a / $b,
            self::Diferencia => ($a ?? 0.0) - ($b ?? 0.0),
            self::Suma => ($a ?? 0.0) + ($b ?? 0.0),
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $o): array => ['value' => $o->value, 'label' => $o->label()], self::cases());
    }
}
