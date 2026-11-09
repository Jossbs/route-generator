<?php

declare(strict_types=1);

namespace App\Services\RouteComposition\Support;

/**
 * El ritmo de referencia: 18 horas por semana, tres horas durante seis días.
 *
 * De aquí salen dos cosas del documento anual: cuántos cursos caben en su año y
 * dónde termina cada mes. Un plan de 746 horas para alguien con cinco a la semana
 * no es una recomendación, es una lista que va a abandonar en marzo.
 *
 * Los cursos son autogestivos: esto no es un calendario. Es la cuenta que hace
 * creíble la secuencia —«mes 3 sugerido»— para quien la va a seguir.
 */
final readonly class StudyPace
{
    /** Ritmo fijo de recomendación: tres horas diarias, seis días por semana. */
    public const DEFAULT_WEEKLY_HOURS = 18;

    public const MIN_WEEKLY_HOURS = 1;

    public const MAX_WEEKLY_HOURS = 40;

    /** 52.14 semanas entre doce meses. La misma cifra con la que el PDF dice duraciones. */
    public const WEEKS_PER_MONTH = 4.345;

    public function __construct(public int $weeklyHours) {}

    public static function orDefault(?int $weeklyHours): self
    {
        return new self($weeklyHours ?? self::DEFAULT_WEEKLY_HOURS);
    }

    /** Horas que caben en un mes a este ritmo. */
    public function monthlyHours(): float
    {
        return $this->weeklyHours * self::WEEKS_PER_MONTH;
    }

    /** Horas que caben en el recorrido completo. */
    public function capacity(int $months): int
    {
        return (int) floor($this->monthlyHours() * $months);
    }
}
