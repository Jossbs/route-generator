<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kpis;

use App\Enums\Kpis\KpiPeriodType;
use App\Http\Controllers\Controller;
use App\Services\Kpis\ClosedPeriods;
use App\Services\Kpis\Period;
use App\Services\Kpis\SalesRoster;
use App\Services\Kpis\Scope;
use App\Services\Kpis\Scoreboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El tablero: el avance de cada KPI en el periodo y el alcance elegidos.
 *
 * Lo ve todo ventas. Por URL llegan `periodo` (dia, semana, mes, trimestre),
 * `fecha` (cualquier día dentro del periodo) y `alcance` (general, equipo:N,
 * persona:N), para que un enlace al tablero de un equipo en un mes se pueda
 * mandar tal cual.
 */
class DashboardController extends Controller
{
    public function index(Request $request, Scoreboard $scoreboard, SalesRoster $roster, ClosedPeriods $closed): Response
    {
        $periodo = Period::fromInput($request->query('periodo'), $request->query('fecha'));
        $alcance = Scope::fromInput($request->query('alcance'));
        $hoy = Period::today();

        return Inertia::render('kpis/tablero', [
            'periodo' => $periodo->toArray(),
            'anterior' => $periodo->previous()->start->toDateString(),
            'siguiente' => $periodo->next()->start->toDateString(),
            'esActual' => $periodo->isCurrent($hoy),
            'hoy' => $hoy->toDateString(),
            'periodos' => KpiPeriodType::options(),
            'alcance' => $alcance->key(),
            'alcanceNombre' => $roster->scopeLabel($alcance),
            'alcances' => $roster->scopeOptions(),
            'kpis' => $scoreboard->build($periodo, $alcance, $hoy),
            // Un mes cerrado se sigue viendo en vivo: con los ajustes posteriores.
            'mesCerrado' => $periodo->type !== KpiPeriodType::Trimestre && $closed->isClosed($periodo->end),
            'puedeAdministrar' => Gate::allows('manage-kpis'),
        ]);
    }
}
