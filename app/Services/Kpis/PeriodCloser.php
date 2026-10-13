<?php

declare(strict_types=1);

namespace App\Services\Kpis;

use App\Enums\Kpis\KpiPeriodType;
use App\Models\KpiPeriodClosure;
use App\Models\SalesTeam;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Cierra un mes: congela sus números y a partir de ahí todo cambio es un ajuste.
 *
 * El snapshot guarda lo que se reportó —el total, cada equipo y el ranking con la
 * meta de cada quien— porque es sobre eso que se pagará comisión cuando llegue la
 * segunda etapa. Solo se cierra un mes que ya terminó.
 */
final class PeriodCloser
{
    public function __construct(
        private readonly Scoreboard $scoreboard,
        private readonly ClosedPeriods $closed,
    ) {}

    public function close(CarbonImmutable $monthStart, User $by): KpiPeriodClosure
    {
        $mes = Period::containing(KpiPeriodType::Mes, $monthStart);

        if (! $mes->isPast(Period::today())) {
            throw ValidationException::withMessages([
                'month' => 'Solo se puede cerrar un mes que ya terminó.',
            ]);
        }

        if ($this->closed->isClosed($mes->start)) {
            throw ValidationException::withMessages([
                'month' => 'Ese mes ya está cerrado.',
            ]);
        }

        $equipos = SalesTeam::query()->orderBy('name')->get()->map(fn (SalesTeam $equipo): array => [
            'id' => $equipo->id,
            'name' => $equipo->name,
            'kpis' => $this->scoreboard->build($mes, Scope::team($equipo->id), detail: false),
        ])->all();

        $cierre = KpiPeriodClosure::create([
            'period_start' => $mes->start->toDateString(),
            'snapshot' => [
                'period' => $mes->toArray(),
                'general' => $this->scoreboard->build($mes, Scope::general(), detail: false),
                'teams' => $equipos,
            ],
            'closed_by' => $by->id,
            'closed_at' => now(),
        ]);

        $this->closed->forget();

        return $cierre;
    }
}
