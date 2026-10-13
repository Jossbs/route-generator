<?php

declare(strict_types=1);

use App\Enums\Kpis\KpiPeriodType;
use App\Enums\Kpis\MeasureUnit;
use App\Services\Kpis\Period;
use App\Services\Kpis\Progress;
use Carbon\CarbonImmutable;

/*
 * Motivar sin desmeritar: el avance se compara con lo esperado a hoy y, si va
 * atrás, dice cuánto falta por día, nunca «atrasado» (decidido el 2026-09-26).
 */

beforeEach(function () {
    $this->mes = Period::containing(KpiPeriodType::Mes, CarbonImmutable::parse('2026-09-01'));
});

it('el día 10 de 30, llevar la tercera parte es ir en ritmo', function () {
    $p = Progress::for(10, 30, $this->mes, CarbonImmutable::parse('2026-09-10'), summable: true);

    expect($p->expected)->toBe(10.0)
        ->and($p->status)->toBe('en_ritmo')
        ->and(round($p->percent, 2))->toBe(33.33)
        ->and($p->milestone)->toBe(25)
        ->and($p->message(MeasureUnit::Piezas))->toBe('Vas en ritmo: 10 de 30.');
});

it('si va atrás, dice cuánto por día le falta, sin regañar', function () {
    // Día 21: faltan 10 días contando hoy y 20 ventas.
    $p = Progress::for(10, 30, $this->mes, CarbonImmutable::parse('2026-09-21'), summable: true);

    expect($p->status)->toBe('por_alcanzar')
        ->and($p->remainingDays)->toBe(10)
        ->and($p->perDayNeeded)->toBe(2.0)
        ->and($p->message(MeasureUnit::Piezas))->toBe('Llevas 10 de 30. Con 2 por día llegas a la meta.')
        ->and($p->message(MeasureUnit::Piezas))->not->toContain('atras');
});

it('cerca es a 85 % de lo esperado o más', function () {
    $p = Progress::for(9, 30, $this->mes, CarbonImmutable::parse('2026-09-10'), summable: true);

    expect($p->status)->toBe('cerca');
});

it('celebra la meta alcanzada aunque falten días', function () {
    $p = Progress::for(31, 30, $this->mes, CarbonImmutable::parse('2026-09-25'), summable: true);

    expect($p->status)->toBe('logrado')
        ->and($p->milestone)->toBe(100)
        ->and($p->perDayNeeded)->toBeNull()
        ->and($p->message(MeasureUnit::Piezas))->toBe('¡Meta alcanzada! 31 de 30.');
});

it('un periodo pasado se lee como cierre', function () {
    $p = Progress::for(24, 30, $this->mes, CarbonImmutable::parse('2026-10-05'), summable: true);

    expect($p->expected)->toBe(30.0)
        ->and($p->message(MeasureUnit::Piezas))->toBe('Cerró con 24 de 30: 80 % de la meta.');
});

it('sin meta solo dice el valor', function () {
    $p = Progress::for(4199, null, $this->mes, CarbonImmutable::parse('2026-09-10'), summable: true);

    expect($p->status)->toBe('sin_meta')
        ->and($p->message(MeasureUnit::Dinero))->toBe('Llevas $4,199.');
});

it('para lo que conviene que baje, lo esperado es un tope', function () {
    $bien = Progress::for(2, 30, $this->mes, CarbonImmutable::parse('2026-09-10'), summable: true, higherIsBetter: false);
    $mal = Progress::for(15, 30, $this->mes, CarbonImmutable::parse('2026-09-10'), summable: true, higherIsBetter: false);

    expect($bien->status)->toBe('en_ritmo')
        ->and($mal->status)->toBe('por_alcanzar');
});
