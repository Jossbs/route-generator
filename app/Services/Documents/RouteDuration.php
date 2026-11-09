<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Services\RouteComposition\Support\StudyPace;
use Illuminate\Support\Number;
use NumberFormatter;

/** Una misma estimación para el PDF, el audio y el mensaje escrito. */
final class RouteDuration
{
    /** @param array<string, mixed> $payload */
    public static function weeklyHours(array $payload): int
    {
        return StudyPace::orDefault(
            isset($payload['membresia']['weekly_hours']) ? (int) $payload['membresia']['weekly_hours'] : null,
        )->weeklyHours;
    }

    /** @param array<string, mixed> $payload */
    public static function for(array $payload, bool $spoken = false): ?string
    {
        $hours = (int) ($payload['ruta']['totales']['horas_netas'] ?? 0);

        if ($hours <= 0) {
            return null;
        }

        $weeks = (int) ceil($hours / self::weeklyHours($payload));

        if ($weeks <= 1) {
            return 'una semana';
        }

        if ($weeks <= 8) {
            return self::number($weeks, $spoken).' semanas';
        }

        $months = $weeks / StudyPace::WEEKS_PER_MONTH;
        $whole = (int) floor($months);
        $remainder = $months - $whole;

        if ($remainder >= 0.75) {
            $whole++;
            $remainder = 0.0;
        }

        $half = $remainder >= 0.25;

        if ($whole === 1) {
            return $half ? 'mes y medio' : 'un mes';
        }

        return self::number($whole, $spoken).' meses'.($half ? ' y medio' : '');
    }

    public static function number(int $number, bool $spoken = true): string
    {
        return $spoken && class_exists(NumberFormatter::class)
            ? (string) Number::spell($number, locale: 'es')
            : (string) $number;
    }
}
