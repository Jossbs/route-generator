<?php

declare(strict_types=1);

namespace App\Services\Kpis;

use App\Enums\Kpis\SalesRole;
use App\Enums\Kpis\TargetScope;
use App\Models\SalesAssignment;
use App\Models\SalesEvent;
use App\Models\SalesTeam;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Quién entra en el ranking de un periodo.
 *
 * Los asesores con un tramo de asesor vigente en algún día del periodo, más
 * cualquiera que tenga eventos acreditados en él: alguien que vendió y a quien
 * todavía no se le dio de alta en un equipo sigue apareciendo, porque su venta
 * sí cuenta en el total.
 */
final class SalesRoster
{
    /**
     * @return list<array{id: int, name: string, team: string|null}>
     */
    public function advisors(CarbonImmutable $from, CarbonImmutable $to, Scope $scope): array
    {
        $tramos = SalesAssignment::query()
            ->with(['user:id,name', 'team:id,name'])
            ->where('role', SalesRole::Asesor->value)
            ->overlapping($from->toDateString(), $to->toDateString())
            ->when($scope->type === TargetScope::Equipo, fn ($q) => $q->where('sales_team_id', $scope->id))
            ->when($scope->type === TargetScope::Persona, fn ($q) => $q->where('user_id', $scope->id))
            ->orderBy('starts_on')
            ->get();

        // Con dos tramos en el periodo, el que se ve es el más reciente.
        $personas = [];

        foreach ($tramos as $tramo) {
            $personas[$tramo->user_id] = [
                'id' => $tramo->user_id,
                'name' => $tramo->user->name,
                'team' => $tramo->team?->name,
            ];
        }

        if ($scope->type === TargetScope::General) {
            $conEventos = SalesEvent::query()
                ->counting()
                ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])
                ->whereNotIn('user_id', array_keys($personas))
                ->distinct()
                ->pluck('user_id');

            foreach (User::query()->whereIn('id', $conEventos)->get(['id', 'name']) as $usuario) {
                $personas[$usuario->id] = ['id' => $usuario->id, 'name' => $usuario->name, 'team' => null];
            }
        }

        if ($scope->type === TargetScope::Persona && $personas === []) {
            $usuario = User::query()->find($scope->id, ['id', 'name']);

            if ($usuario !== null) {
                $personas[$usuario->id] = ['id' => $usuario->id, 'name' => $usuario->name, 'team' => null];
            }
        }

        return array_values($personas);
    }

    /**
     * Los alcances que se pueden elegir en el tablero.
     *
     * @return list<array{value: string, label: string, group: string}>
     */
    public function scopeOptions(): array
    {
        $opciones = [['value' => 'general', 'label' => 'Todo ventas', 'group' => 'general']];

        foreach (SalesTeam::query()->where('is_active', true)->orderBy('name')->get() as $equipo) {
            $opciones[] = ['value' => 'equipo:'.$equipo->id, 'label' => $equipo->name, 'group' => 'Equipos'];
        }

        $personas = User::query()
            ->whereIn('id', SalesAssignment::query()->select('user_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        foreach ($personas as $persona) {
            $opciones[] = ['value' => 'persona:'.$persona->id, 'label' => $persona->name, 'group' => 'Personas'];
        }

        return $opciones;
    }

    /** El nombre del alcance, para títulos y reportes. */
    public function scopeLabel(Scope $scope): string
    {
        return match ($scope->type) {
            TargetScope::General => 'Todo ventas',
            TargetScope::Equipo => SalesTeam::query()->find($scope->id)->name ?? 'Equipo',
            TargetScope::Persona => User::query()->find($scope->id)->name ?? 'Persona',
        };
    }
}
