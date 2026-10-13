<?php

declare(strict_types=1);

use App\Models\SalesAssignment;
use App\Models\SalesTeam;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_kpi_admin' => true]));
    $this->advisor = User::factory()->create();
    $this->team = SalesTeam::create(['name' => 'Equipo A']);
});

it('cierra el tramo anterior al cambiar de equipo', function () {
    $anterior = SalesAssignment::create([
        'user_id' => $this->advisor->id,
        'sales_team_id' => $this->team->id,
        'role' => 'asesor',
        'starts_on' => '2026-01-01',
    ]);

    $this->post('/kpis/configuracion/asignaciones', [
        'user_id' => $this->advisor->id,
        'sales_team_id' => SalesTeam::create(['name' => 'Equipo B'])->id,
        'role' => 'asesor',
        'starts_on' => '2026-02-01',
    ])->assertSessionHasNoErrors();

    expect($anterior->refresh()->ends_on->toDateString())->toBe('2026-01-31')
        ->and(SalesAssignment::count())->toBe(2);
});

it('no reabre ni extiende un tramo sobre una asignación posterior', function (?string $fin) {
    $anterior = SalesAssignment::create([
        'user_id' => $this->advisor->id,
        'sales_team_id' => $this->team->id,
        'role' => 'asesor',
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-01-31',
    ]);
    SalesAssignment::create([
        'user_id' => $this->advisor->id,
        'sales_team_id' => SalesTeam::create(['name' => 'Equipo B'])->id,
        'role' => 'asesor',
        'starts_on' => '2026-02-01',
    ]);

    $this->put('/kpis/configuracion/asignaciones/'.$anterior->id, ['ends_on' => $fin])
        ->assertSessionHasErrors('ends_on');

    expect($anterior->refresh()->ends_on->toDateString())->toBe('2026-01-31');
})->with([null, '2026-02-01', '2026-03-01']);
