<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kpis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Kpis\Concerns\RespondsWithToast;
use App\Models\SalesTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Los equipos no se borran: se desactivan. Sus metas y la historia de quién
 * perteneció a ellos siguen explicando los números de los meses pasados.
 */
class SalesTeamController extends Controller
{
    use RespondsWithToast;

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:sales_teams,name'],
        ], [], ['name' => 'nombre']);

        SalesTeam::create($datos);

        return $this->backWithToast("Se creó el equipo «{$datos['name']}».");
    }

    public function update(Request $request, SalesTeam $team): RedirectResponse
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('sales_teams', 'name')->ignore($team->id)],
            'is_active' => ['boolean'],
        ], [], ['name' => 'nombre']);

        $team->update($datos);

        return $this->backWithToast('Equipo guardado.');
    }
}
