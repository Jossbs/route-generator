<?php

declare(strict_types=1);

namespace App\Services\Kpis;

use App\Enums\Kpis\KpiPeriodType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Un corte de tiempo del tablero: un día, una semana, un mes o un trimestre.
 *
 * Trimestres de calendario, semanas de lunes a domingo y los siete días cuentan
 * (decidido el 2026-09-26). Las fechas son días sin hora en la zona de ventas; ver
 * `today()`.
 */
final class Period
{
    public function __construct(
        public readonly KpiPeriodType $type,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {}

    /** Hoy en México, sin hora. */
    public static function today(): CarbonImmutable
    {
        $hoy = now((string) config('kpis.timezone'))->toDateString();

        return CarbonImmutable::parse($hoy)->startOfDay();
    }

    public static function containing(KpiPeriodType $type, CarbonInterface $date): self
    {
        $dia = CarbonImmutable::parse($date->toDateString())->startOfDay();

        return match ($type) {
            KpiPeriodType::Dia => new self($type, $dia, $dia),
            KpiPeriodType::Semana => new self($type, $dia->startOfWeek(CarbonInterface::MONDAY), $dia->endOfWeek(CarbonInterface::SUNDAY)->startOfDay()),
            KpiPeriodType::Mes => new self($type, $dia->startOfMonth(), $dia->endOfMonth()->startOfDay()),
            KpiPeriodType::Trimestre => new self($type, $dia->firstOfQuarter(), $dia->lastOfQuarter()->startOfDay()),
        };
    }

    /** El periodo pedido por URL, o el mes en curso si no se entiende lo que llegó. */
    public static function fromInput(?string $type, ?string $date): self
    {
        $tipo = KpiPeriodType::tryFrom((string) $type) ?? KpiPeriodType::Mes;

        try {
            $fecha = filled($date) ? CarbonImmutable::parse((string) $date) : self::today();
        } catch (\Throwable) {
            $fecha = self::today();
        }

        return self::containing($tipo, $fecha);
    }

    public function previous(): self
    {
        return self::containing($this->type, $this->start->subDay());
    }

    public function next(): self
    {
        return self::containing($this->type, $this->end->addDay());
    }

    public function days(): int
    {
        return (int) $this->start->diffInDays($this->end) + 1;
    }

    /** Los días transcurridos hasta hoy inclusive, entre cero y la duración. */
    public function elapsedDays(CarbonImmutable $today): int
    {
        if ($today->lt($this->start)) {
            return 0;
        }

        if ($today->gt($this->end)) {
            return $this->days();
        }

        return (int) $this->start->diffInDays($today) + 1;
    }

    public function contains(CarbonInterface $date): bool
    {
        $dia = $date->toDateString();

        return $dia >= $this->start->toDateString() && $dia <= $this->end->toDateString();
    }

    public function isCurrent(CarbonImmutable $today): bool
    {
        return $this->contains($today);
    }

    public function isPast(CarbonImmutable $today): bool
    {
        return $this->end->lt($today);
    }

    /** @return list<CarbonImmutable> */
    public function eachDay(): array
    {
        $dias = [];

        for ($dia = $this->start; $dia->lte($this->end); $dia = $dia->addDay()) {
            $dias[] = $dia;
        }

        return $dias;
    }

    /** Como se lee en el tablero y en el resumen. */
    public function label(): string
    {
        $inicio = $this->start->settings(['locale' => 'es']);
        $fin = $this->end->settings(['locale' => 'es']);

        return match ($this->type) {
            KpiPeriodType::Dia => $inicio->translatedFormat('l j \d\e F \d\e Y'),
            KpiPeriodType::Semana => $inicio->month === $fin->month
                ? $inicio->translatedFormat('j').'–'.$fin->translatedFormat('j \d\e F \d\e Y')
                : $inicio->translatedFormat('j \d\e F').' – '.$fin->translatedFormat('j \d\e F \d\e Y'),
            KpiPeriodType::Mes => $inicio->translatedFormat('F \d\e Y'),
            KpiPeriodType::Trimestre => 'T'.$inicio->quarter.' '.$inicio->year
                .' ('.$inicio->translatedFormat('M').'–'.$fin->translatedFormat('M').')',
        };
    }

    /** @return array{type: string, start: string, end: string, label: string} */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'start' => $this->start->toDateString(),
            'end' => $this->end->toDateString(),
            'label' => $this->label(),
        ];
    }
}
