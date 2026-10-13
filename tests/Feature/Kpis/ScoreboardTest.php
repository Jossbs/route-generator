<?php

declare(strict_types=1);

use App\Enums\Kpis\KpiPeriodType;
use App\Enums\Kpis\MeasureAggregation;
use App\Enums\Kpis\TargetScope;
use App\Models\User;
use App\Services\Kpis\Period;
use App\Services\Kpis\Scope;
use App\Services\Kpis\Scoreboard;
use Carbon\CarbonImmutable;
use Tests\Support\KpiFactory;

/*
 * El tablero: ranking abierto, cada quien contra su meta, con rachas y mejor marca
 * para que el avance se note aunque el mes vaya difícil (2026-09-26).
 */

beforeEach(function () {
    $this->venta = KpiFactory::saleType();
    $this->kpi = KpiFactory::kpi();
    $this->conteo = KpiFactory::aggregate($this->kpi, $this->venta, MeasureAggregation::Conteo);
    $this->norte = KpiFactory::team('Norte');
    $this->ana = User::factory()->create(['name' => 'Ana']);
    $this->beto = User::factory()->create(['name' => 'Beto']);
    $this->caro = User::factory()->create(['name' => 'Caro']);
    foreach ([$this->ana, $this->beto, $this->caro] as $u) {
        KpiFactory::assign($u, $this->norte, '2026-01-01');
    }
    $this->hoy = CarbonImmutable::parse('2026-09-20');
    $this->mes = Period::containing(KpiPeriodType::Mes, $this->hoy);
});

function medidaDelTablero(array $kpis): array
{
    return $kpis[0]['measures'][0];
}

it('ordena el ranking y los empates comparten lugar', function () {
    foreach (['2026-09-01', '2026-09-02'] as $d) {
        KpiFactory::sale($this->venta, $this->ana, $d);
        KpiFactory::sale($this->venta, $this->beto, $d);
    }
    KpiFactory::sale($this->venta, $this->caro, '2026-09-03');

    $ranking = medidaDelTablero(app(Scoreboard::class)->build($this->mes, Scope::general(), $this->hoy))['ranking'];

    expect(array_column($ranking, 'name'))->toBe(['Ana', 'Beto', 'Caro'])
        ->and(array_column($ranking, 'position'))->toBe([1, 1, 3]);
});

it('incluye a quien no vendió, con cero', function () {
    KpiFactory::sale($this->venta, $this->ana, '2026-09-01');

    $ranking = medidaDelTablero(app(Scoreboard::class)->build($this->mes, Scope::general(), $this->hoy))['ranking'];

    expect($ranking)->toHaveCount(3)->and(end($ranking)['value'])->toBe(0.0);
});

it('mide a cada quien contra su propia meta', function () {
    KpiFactory::target($this->conteo, KpiPeriodType::Mes, '2026-09-01', 10, TargetScope::Persona, $this->ana->id);
    KpiFactory::sale($this->venta, $this->ana, '2026-09-01');

    $fila = collect(medidaDelTablero(app(Scoreboard::class)->build($this->mes, Scope::general(), $this->hoy))['ranking'])
        ->firstWhere('name', 'Ana');

    expect($fila['target'])->toBe(10.0)->and($fila['percent'])->toBe(10.0);
});

it('cuenta la racha de días seguidos con venta, sin cortarla porque hoy aún no vende', function () {
    foreach (['2026-09-16', '2026-09-17', '2026-09-18', '2026-09-19'] as $d) {
        KpiFactory::sale($this->venta, $this->ana, $d);
    }
    KpiFactory::sale($this->venta, $this->beto, '2026-09-19');
    KpiFactory::sale($this->venta, $this->beto, '2026-09-17');

    $ranking = collect(medidaDelTablero(app(Scoreboard::class)->build($this->mes, Scope::general(), $this->hoy))['ranking'])->keyBy('name');

    expect($ranking['Ana']['streak'])->toBe(4)
        ->and($ranking['Beto']['streak'])->toBe(1)
        ->and($ranking['Caro']['streak'])->toBe(0);
});

it('reconoce cuando alguien iguala o supera su mejor mes', function () {
    KpiFactory::sale($this->venta, $this->ana, '2026-07-05');
    KpiFactory::sale($this->venta, $this->ana, '2026-07-06');
    KpiFactory::sale($this->venta, $this->ana, '2026-09-01');
    KpiFactory::sale($this->venta, $this->ana, '2026-09-02');

    $ana = collect(medidaDelTablero(app(Scoreboard::class)->build($this->mes, Scope::general(), $this->hoy))['ranking'])->firstWhere('name', 'Ana');

    expect($ana['best_month'])->toBe(['month' => '2026-07-01', 'value' => 2.0])
        ->and($ana['is_personal_best'])->toBeTrue();
});

it('la serie acumula por día y deja sin valor los días que no han llegado', function () {
    KpiFactory::target($this->conteo, KpiPeriodType::Mes, '2026-09-01', 30);
    KpiFactory::sale($this->venta, $this->ana, '2026-09-01');
    KpiFactory::sale($this->venta, $this->ana, '2026-09-03');

    $serie = medidaDelTablero(app(Scoreboard::class)->build($this->mes, Scope::general(), $this->hoy))['series'];

    expect($serie)->toHaveCount(30)
        ->and($serie[2])->toBe(['date' => '2026-09-03', 'value' => 1.0, 'cumulative' => 2.0, 'expected' => 3.0])
        ->and($serie[25]['value'])->toBeNull();
});
