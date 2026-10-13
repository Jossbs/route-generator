<?php

declare(strict_types=1);

namespace App\Services\Kpis;

use App\Enums\Kpis\KpiPeriodType;
use App\Enums\Kpis\TargetScope;
use App\Models\KpiMeasure;
use App\Models\KpiTarget;
use App\Models\SalesTeam;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Guarda metas sin perder las anteriores.
 *
 * Cambiar una meta cierra la versión vigente (`replaced_at`) y crea otra con
 * autor y motivo; borrarla solo cierra la vigente. Una celda que no cambió no
 * genera versión: el historial registra decisiones, no guardados (decidido el
 * 2026-09-26).
 */
final class TargetBook
{
    public function __construct(private readonly TargetResolver $resolver) {}

    /**
     * @param  list<array{scope: string, id: int|null, period_type: string, period_start: string, value: float|int|string|null}>  $cells
     * @return int cuántas metas cambiaron.
     */
    public function save(KpiMeasure $measure, array $cells, User $by, ?string $reason): int
    {
        $cambios = 0;

        DB::transaction(function () use ($measure, $cells, $by, $reason, &$cambios): void {
            foreach ($cells as $index => $celda) {
                $alcance = TargetScope::from($celda['scope']);
                $id = $celda['id'] ?? null;
                $existe = match ($alcance) {
                    TargetScope::General => $id === null,
                    TargetScope::Equipo => $id !== null && SalesTeam::query()->whereKey($id)->exists(),
                    TargetScope::Persona => $id !== null && User::query()->whereKey($id)->exists(),
                };

                if (! $existe) {
                    throw ValidationException::withMessages([
                        "cells.{$index}.id" => 'Elige una persona o un equipo existente; la meta general no lleva identificador.',
                    ]);
                }

                $tipo = KpiPeriodType::from($celda['period_type']);
                $inicio = CarbonImmutable::parse($celda['period_start']);
                $inicio = $tipo === KpiPeriodType::Trimestre ? $inicio->firstOfQuarter() : $inicio->startOfMonth();
                $valor = $celda['value'] === null || $celda['value'] === '' ? null : round((float) $celda['value'], 2);

                $vigente = KpiTarget::query()
                    ->current()
                    ->where('kpi_measure_id', $measure->id)
                    ->where('scope', $alcance->value)
                    ->where('sales_team_id', $alcance === TargetScope::Equipo ? $celda['id'] : null)
                    ->where('user_id', $alcance === TargetScope::Persona ? $celda['id'] : null)
                    ->where('period_type', $tipo->value)
                    ->whereDate('period_start', $inicio->toDateString())
                    ->lockForUpdate()
                    ->first();

                if ($vigente !== null && $valor !== null && (float) $vigente->value === $valor) {
                    continue;
                }

                if ($vigente === null && $valor === null) {
                    continue;
                }

                $vigente?->update(['replaced_at' => now()]);

                if ($valor !== null) {
                    KpiTarget::create([
                        'kpi_measure_id' => $measure->id,
                        'scope' => $alcance,
                        'sales_team_id' => $alcance === TargetScope::Equipo ? $celda['id'] : null,
                        'user_id' => $alcance === TargetScope::Persona ? $celda['id'] : null,
                        'period_type' => $tipo,
                        'period_start' => $inicio->toDateString(),
                        'value' => $valor,
                        'reason' => $reason,
                        'created_by' => $by->id,
                    ]);
                }

                $cambios++;
            }
        });

        $this->resolver->forget();

        return $cambios;
    }
}
