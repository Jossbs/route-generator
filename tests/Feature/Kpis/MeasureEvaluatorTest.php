<?php

declare(strict_types=1);

use App\Enums\Kpis\FormulaOperator;
use App\Enums\Kpis\MeasureAggregation;
use App\Enums\Kpis\MeasureUnit;
use App\Enums\Kpis\SalesEventStatus;
use App\Models\User;
use App\Services\Kpis\MeasureEvaluator;
use App\Services\Kpis\Scope;
use Carbon\CarbonImmutable;
use Tests\Support\KpiFactory;

/*
 * El intérprete de medidas: cualquier KPI se arma con conteo, suma, promedio,
 * mínimo, máximo, filtros y fórmulas, sin tocar código (decidido el 2026-09-26).
 */

beforeEach(function () {
    $this->evaluator = app(MeasureEvaluator::class);
    $this->venta = KpiFactory::saleType();
    $this->kpi = KpiFactory::kpi();
    $this->ana = User::factory()->create(['name' => 'Ana']);
    $this->beto = User::factory()->create(['name' => 'Beto']);
    $this->desde = CarbonImmutable::parse('2026-09-01');
    $this->hasta = CarbonImmutable::parse('2026-09-30');
});

it('cuenta, suma, promedia y saca mínimo y máximo de un campo', function () {
    KpiFactory::sale($this->venta, $this->ana, '2026-09-02', 4199);
    KpiFactory::sale($this->venta, $this->ana, '2026-09-10', 999, 'expert_mensual');
    KpiFactory::sale($this->venta, $this->beto, '2026-09-12', 5999, 'duo');

    $valor = fn (MeasureAggregation $a, ?string $campo = null) => $this->evaluator->value(
        KpiFactory::aggregate($this->kpi, $this->venta, $a, $campo), $this->desde, $this->hasta, Scope::general(),
    );

    expect($valor(MeasureAggregation::Conteo))->toBe(3.0)
        ->and($valor(MeasureAggregation::Suma, 'importe'))->toBe(11197.0)
        ->and(round($valor(MeasureAggregation::Promedio, 'importe'), 2))->toBe(3732.33)
        ->and($valor(MeasureAggregation::Minimo, 'importe'))->toBe(999.0)
        ->and($valor(MeasureAggregation::Maximo, 'importe'))->toBe(5999.0);
});

it('solo cuenta lo confirmado y sin anular, dentro del tramo', function () {
    KpiFactory::sale($this->venta, $this->ana, '2026-09-02');
    KpiFactory::sale($this->venta, $this->ana, '2026-09-03', attrs: ['status' => SalesEventStatus::Sugerido]);
    KpiFactory::sale($this->venta, $this->ana, '2026-09-04', attrs: ['status' => SalesEventStatus::Descartado]);
    KpiFactory::sale($this->venta, $this->ana, '2026-09-05', attrs: ['voided_at' => now()]);
    KpiFactory::sale($this->venta, $this->ana, '2026-10-01');

    $conteo = KpiFactory::aggregate($this->kpi, $this->venta, MeasureAggregation::Conteo);

    expect($this->evaluator->value($conteo, $this->desde, $this->hasta, Scope::general()))->toBe(1.0);
});

it('filtra por el valor de un campo', function () {
    KpiFactory::sale($this->venta, $this->ana, '2026-09-02', 4199, 'expert_anual');
    KpiFactory::sale($this->venta, $this->ana, '2026-09-03', 999, 'expert_mensual');
    KpiFactory::sale($this->venta, $this->ana, '2026-09-04', 5999, 'duo');

    $anuales = KpiFactory::aggregate($this->kpi, $this->venta, MeasureAggregation::Conteo, attrs: [
        'slug' => 'anuales', 'filters' => [['field' => 'plan', 'op' => '=', 'value' => 'expert_anual']],
    ]);
    $anualesODuo = KpiFactory::aggregate($this->kpi, $this->venta, MeasureAggregation::Conteo, attrs: [
        'slug' => 'anual-duo', 'filters' => [['field' => 'plan', 'op' => 'in', 'value' => ['expert_anual', 'duo']]],
    ]);
    $grandes = KpiFactory::aggregate($this->kpi, $this->venta, MeasureAggregation::Suma, 'importe', [
        'slug' => 'grandes', 'filters' => [['field' => 'importe', 'op' => '>=', 'value' => 4000]],
    ]);

    expect($this->evaluator->value($anuales, $this->desde, $this->hasta, Scope::general()))->toBe(1.0)
        ->and($this->evaluator->value($anualesODuo, $this->desde, $this->hasta, Scope::general()))->toBe(2.0)
        ->and($this->evaluator->value($grandes, $this->desde, $this->hasta, Scope::general()))->toBe(10198.0);
});

it('acredita al equipo al que pertenecía la persona el día del evento', function () {
    $norte = KpiFactory::team('Norte');
    $sur = KpiFactory::team('Sur');
    KpiFactory::assign($this->ana, $norte, '2026-01-01', '2026-09-15');
    KpiFactory::assign($this->ana, $sur, '2026-09-16');

    KpiFactory::sale($this->venta, $this->ana, '2026-09-10');
    KpiFactory::sale($this->venta, $this->ana, '2026-09-20');
    KpiFactory::sale($this->venta, $this->ana, '2026-09-21');

    $conteo = KpiFactory::aggregate($this->kpi, $this->venta, MeasureAggregation::Conteo);

    expect($this->evaluator->value($conteo, $this->desde, $this->hasta, Scope::team($norte->id)))->toBe(1.0)
        ->and($this->evaluator->value($conteo, $this->desde, $this->hasta, Scope::team($sur->id)))->toBe(2.0)
        ->and($this->evaluator->value($conteo, $this->desde, $this->hasta, Scope::person($this->ana->id)))->toBe(3.0);
});

it('calcula una razón con los totales, nunca promediando razones', function () {
    $leads = KpiFactory::leadType();
    // Ana: 1 venta de 2 leads (50 %). Beto: 1 de 8 (12.5 %). Equipo: 2 de 10 = 20 %, no 31.25 %.
    KpiFactory::sale($this->venta, $this->ana, '2026-09-02');
    KpiFactory::sale($this->venta, $this->beto, '2026-09-02');
    foreach (range(1, 2) as $i) {
        KpiFactory::event($leads, $this->ana, '2026-09-02');
    }
    foreach (range(1, 8) as $i) {
        KpiFactory::event($leads, $this->beto, '2026-09-03');
    }

    $ventas = KpiFactory::aggregate($this->kpi, $this->venta, MeasureAggregation::Conteo);
    $atendidos = KpiFactory::aggregate($this->kpi, $leads, MeasureAggregation::Conteo, attrs: ['slug' => 'leads']);
    $conversion = KpiFactory::formula($this->kpi, FormulaOperator::Razon, $ventas, $atendidos);

    $porPersona = $this->evaluator->grouped($conversion, $this->desde, $this->hasta, Scope::general(), ['user']);

    expect($this->evaluator->value($conversion, $this->desde, $this->hasta, Scope::general()))->toBe(20.0)
        ->and($porPersona[(string) $this->ana->id])->toBe(50.0)
        ->and($porPersona[(string) $this->beto->id])->toBe(12.5)
        ->and($conversion->isSummable())->toBeFalse();
});

it('una diferencia de dos sumables se sigue sumando', function () {
    $cancelacion = KpiFactory::leadType();
    KpiFactory::sale($this->venta, $this->ana, '2026-09-02');
    KpiFactory::sale($this->venta, $this->ana, '2026-09-03');
    KpiFactory::event($cancelacion, $this->ana, '2026-09-04');

    $ventas = KpiFactory::aggregate($this->kpi, $this->venta, MeasureAggregation::Conteo);
    $cancelaciones = KpiFactory::aggregate($this->kpi, $cancelacion, MeasureAggregation::Conteo, attrs: ['slug' => 'cancelaciones']);
    $netas = KpiFactory::formula($this->kpi, FormulaOperator::Diferencia, $ventas, $cancelaciones, MeasureUnit::Piezas);

    expect($this->evaluator->value($netas, $this->desde, $this->hasta, Scope::general()))->toBe(1.0)
        ->and($netas->isSummable())->toBeTrue();
});

it('una razón sin denominador no vale nada, y una suma sin eventos vale cero', function () {
    $leads = KpiFactory::leadType();
    $ventas = KpiFactory::aggregate($this->kpi, $this->venta, MeasureAggregation::Conteo);
    $atendidos = KpiFactory::aggregate($this->kpi, $leads, MeasureAggregation::Conteo, attrs: ['slug' => 'leads']);
    $conversion = KpiFactory::formula($this->kpi, FormulaOperator::Razon, $ventas, $atendidos);
    $promedio = KpiFactory::aggregate($this->kpi, $this->venta, MeasureAggregation::Promedio, 'importe');

    expect($this->evaluator->value($ventas, $this->desde, $this->hasta, Scope::general()))->toBe(0.0)
        ->and($this->evaluator->value($conversion, $this->desde, $this->hasta, Scope::general()))->toBeNull()
        ->and($this->evaluator->value($promedio, $this->desde, $this->hasta, Scope::general()))->toBeNull();
});

it('agrupa por persona y día en una sola llave', function () {
    KpiFactory::sale($this->venta, $this->ana, '2026-09-02');
    KpiFactory::sale($this->venta, $this->ana, '2026-09-02');
    KpiFactory::sale($this->venta, $this->beto, '2026-09-03');

    $conteo = KpiFactory::aggregate($this->kpi, $this->venta, MeasureAggregation::Conteo);
    $grupos = $this->evaluator->grouped($conteo, $this->desde, $this->hasta, Scope::general(), ['user', 'day']);

    expect($grupos)->toBe([
        $this->ana->id.'|2026-09-02' => 2.0,
        $this->beto->id.'|2026-09-03' => 1.0,
    ]);
});
