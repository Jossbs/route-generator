<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kpis;

use App\Enums\Kpis\SalesRole;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Kpis\Concerns\RespondsWithToast;
use App\Models\SalesAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Quién está en qué equipo y con qué papel, desde cuándo.
 *
 * Mover a alguien de equipo es cerrar su tramo y abrir otro: al dar de alta un
 * tramo nuevo, el vigente de la misma persona se cierra el día anterior. Así la
 * venta de marzo sigue contando para el equipo de marzo (decidido el 2026-09-26).
 */
class SalesAssignmentController extends Controller
{
    use RespondsWithToast;

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'sales_team_id' => ['nullable', 'integer', 'exists:sales_teams,id'],
            'role' => ['required', Rule::enum(SalesRole::class)],
            'starts_on' => ['required', 'date'],
        ], [], ['user_id' => 'persona', 'sales_team_id' => 'equipo', 'role' => 'papel', 'starts_on' => 'fecha de inicio']);

        DB::transaction(function () use ($datos): void {
            User::query()->lockForUpdate()->findOrFail($datos['user_id']);
            $inicio = CarbonImmutable::parse($datos['starts_on']);

            $solapado = SalesAssignment::query()
                ->where('user_id', $datos['user_id'])
                ->where('starts_on', '>=', $inicio->toDateString())
                ->exists();

            if ($solapado) {
                throw ValidationException::withMessages([
                    'starts_on' => 'Esa persona ya tiene un tramo que empieza ese día o después. Corrige ese tramo en lugar de abrir otro.',
                ]);
            }

            SalesAssignment::query()
                ->where('user_id', $datos['user_id'])
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $inicio->toDateString()))
                ->update(['ends_on' => $inicio->subDay()->toDateString()]);

            SalesAssignment::create($datos);
        });

        return $this->backWithToast('Asignación guardada.');
    }

    /** Solo se corrige el cierre de un tramo: darlo por terminado o reabrirlo. */
    public function update(Request $request, SalesAssignment $assignment): RedirectResponse
    {
        $datos = $request->validate([
            'ends_on' => ['nullable', 'date', 'after_or_equal:'.$assignment->starts_on->toDateString()],
        ], [], ['ends_on' => 'fecha de fin']);

        DB::transaction(function () use ($assignment, $datos): void {
            User::query()->lockForUpdate()->findOrFail($assignment->user_id);
            $assignment = SalesAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $fin = $datos['ends_on'] ?? null;

            $solapado = SalesAssignment::query()
                ->where('user_id', $assignment->user_id)
                ->whereKeyNot($assignment->id)
                ->where(fn ($query) => $query->whereNull('ends_on')
                    ->orWhere('ends_on', '>=', $assignment->starts_on->toDateString()))
                ->when($fin !== null, fn ($query) => $query->where('starts_on', '<=', $fin))
                ->exists();

            if ($solapado) {
                throw ValidationException::withMessages([
                    'ends_on' => 'El tramo se cruza con otra asignación de esa persona. Termínalo antes de que empiece la siguiente.',
                ]);
            }

            $assignment->update(['ends_on' => $fin]);
        });

        return $this->backWithToast('Asignación guardada.');
    }
}
