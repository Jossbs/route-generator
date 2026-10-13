<?php

declare(strict_types=1);

namespace App\Enums\Kpis;

/**
 * Los cortes de tiempo del tablero.
 *
 * Trimestres de calendario (ene–mar), semanas de lunes a domingo y los siete días
 * hábiles: ventas no para (decidido el 2026-09-26).
 */
enum KpiPeriodType: string
{
    case Dia = 'dia';
    case Semana = 'semana';
    case Mes = 'mes';
    case Trimestre = 'trimestre';

    public function label(): string
    {
        return match ($this) {
            self::Dia => 'Día',
            self::Semana => 'Semana',
            self::Mes => 'Mes',
            self::Trimestre => 'Trimestre',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $p): array => ['value' => $p->value, 'label' => $p->label()], self::cases());
    }
}
