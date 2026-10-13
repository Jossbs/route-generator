<?php

declare(strict_types=1);

namespace App\Services\Kpis;

use App\Models\KpiPeriodClosure;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Qué meses están cerrados, y la regla de qué se puede hacer sobre ellos.
 *
 * Sobre un mes cerrado nada se edita en silencio: cualquier alta, cambio o
 * anulación entra como ajuste y exige motivo (decidido el 2026-09-26). Los números
 * que se reportaron al cerrar se quedan en el snapshot del cierre.
 */
final class ClosedPeriods
{
    /** @var array<string, bool> */
    private array $cache = [];

    public function isClosed(CarbonInterface $date): bool
    {
        $mes = $date->copy()->startOfMonth()->toDateString();

        return $this->cache[$mes] ??= KpiPeriodClosure::query()->whereDate('period_start', $mes)->exists();
    }

    /**
     * Si alguna de las fechas cae en un mes cerrado, exige el motivo.
     *
     * @return bool si el cambio es un ajuste sobre un mes cerrado.
     *
     * @throws ValidationException
     */
    public function guard(?string $reason, CarbonInterface ...$dates): bool
    {
        foreach ($dates as $fecha) {
            if (! $this->isClosed($fecha)) {
                continue;
            }

            if (blank($reason)) {
                throw ValidationException::withMessages([
                    'adjustment_reason' => 'El mes de '.$fecha->copy()->settings(['locale' => 'es'])->translatedFormat('F').' ya está cerrado: escribe el motivo del ajuste.',
                ]);
            }

            return true;
        }

        return false;
    }

    public function forget(): void
    {
        $this->cache = [];
    }
}
