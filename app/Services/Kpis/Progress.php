<?php

declare(strict_types=1);

namespace App\Services\Kpis;

use App\Enums\Kpis\MeasureUnit;
use Carbon\CarbonImmutable;

/**
 * El avance de una medida contra su meta, y cómo decirlo sin desmotivar.
 *
 * El porcentaje solo no alcanza: el día 10 de un mes de 30, llevar 33 % es ir en
 * ritmo, y un 33 % en rojo desmotiva a quien va bien. Por eso cada avance trae
 * lo esperado a hoy —la meta repartida por día natural— y, si va atrás, cuánto
 * falta por día para llegar: una tarea concreta en vez de un reproche (decidido
 * el 2026-09-26).
 *
 * Los estados:
 * - `logrado`: ya alcanzó la meta del periodo.
 * - `en_ritmo`: va igual o por encima de lo esperado a hoy.
 * - `cerca`: le falta poco (85 % de lo esperado o más).
 * - `por_alcanzar`: va atrás. Nunca se llama «atrasado» en pantalla.
 * - `sin_meta`: no hay meta que comparar; solo se muestra el valor.
 *
 * Para lo que conviene que baje (cancelaciones), lo esperado es un tope.
 */
final class Progress
{
    public const MILESTONES = [25, 50, 75, 100];

    private function __construct(
        public readonly ?float $value,
        public readonly ?float $target,
        public readonly ?float $expected,
        public readonly ?float $percent,
        public readonly ?float $perDayNeeded,
        public readonly int $remainingDays,
        public readonly string $status,
        public readonly ?int $milestone,
        public readonly bool $closedPeriod,
    ) {}

    public static function for(
        ?float $value,
        ?float $target,
        Period $period,
        CarbonImmutable $today,
        bool $summable,
        bool $higherIsBetter = true,
    ): self {
        $restantes = $period->isPast($today) ? 0 : $period->days() - $period->elapsedDays($today) + ($period->isCurrent($today) ? 1 : 0);
        $cerrado = $period->isPast($today);

        if ($target === null || $target == 0.0) {
            return new self($value, $target, null, null, null, $restantes, 'sin_meta', null, $cerrado);
        }

        $actual = $value ?? 0.0;
        // Lo esperado a hoy. Hoy cuenta como transcurrido: a las 9:00 todavía no
        // se espera la venta del día, pero al cierre sí, y el tablero se lee
        // sobre todo al final de la jornada.
        $esperado = $summable
            ? $target * $period->elapsedDays($today) / $period->days()
            : ($period->elapsedDays($today) > 0 ? $target : 0.0);
        $porcentaje = $actual / $target * 100;

        if ($higherIsBetter) {
            $estado = match (true) {
                $actual >= $target => 'logrado',
                $actual >= $esperado => 'en_ritmo',
                $actual >= $esperado * 0.85 => 'cerca',
                default => 'por_alcanzar',
            };
            $porDia = $summable && $restantes > 0 && $actual < $target ? ($target - $actual) / $restantes : null;
            $hito = null;

            foreach (self::MILESTONES as $m) {
                if ($porcentaje >= $m) {
                    $hito = $m;
                }
            }
        } else {
            $estado = $actual <= $esperado ? 'en_ritmo' : 'por_alcanzar';
            $porDia = null;
            $hito = null;
        }

        return new self($value, $target, $esperado, $porcentaje, $porDia, $restantes, $estado, $hito, $cerrado);
    }

    /** Una frase para la tarjeta y para Telegram. Nunca regaña. */
    public function message(MeasureUnit $unit): string
    {
        $v = ValueFormatter::format($this->value ?? 0.0, $unit);
        $t = ValueFormatter::format($this->target, $unit);

        if ($this->status === 'sin_meta') {
            return "Llevas {$v}.";
        }

        if ($this->status === 'logrado') {
            return "¡Meta alcanzada! {$v} de {$t}.";
        }

        if ($this->closedPeriod) {
            return "Cerró con {$v} de {$t}: ".(int) round($this->percent ?? 0).' % de la meta.';
        }

        if ($this->status === 'en_ritmo') {
            return "Vas en ritmo: {$v} de {$t}.";
        }

        if ($this->perDayNeeded !== null) {
            $porDia = ValueFormatter::format($this->perDayNeeded, $unit);

            return $this->remainingDays === 1
                ? "Llevas {$v} de {$t}. Hoy faltan {$porDia} para la meta."
                : "Llevas {$v} de {$t}. Con {$porDia} por día llegas a la meta.";
        }

        return "Llevas {$v} de {$t}.";
    }

    /** @return array<string, mixed> */
    public function toArray(MeasureUnit $unit): array
    {
        return [
            'value' => $this->value,
            'target' => $this->target,
            'expected' => $this->expected,
            'percent' => $this->percent,
            'per_day_needed' => $this->perDayNeeded,
            'remaining_days' => $this->remainingDays,
            'status' => $this->status,
            'milestone' => $this->milestone,
            'value_label' => ValueFormatter::format($this->value ?? ($this->target === null ? null : 0.0), $unit),
            'target_label' => ValueFormatter::format($this->target, $unit),
            'expected_label' => ValueFormatter::format($this->expected, $unit),
            'message' => $this->message($unit),
        ];
    }
}
