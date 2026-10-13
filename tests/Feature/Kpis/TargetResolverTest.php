<?php

declare(strict_types=1);

use App\Enums\Kpis\FormulaOperator;
use App\Enums\Kpis\KpiPeriodType;
use App\Enums\Kpis\MeasureAggregation;
use App\Enums\Kpis\TargetScope;
use App\Models\User;
use App\Services\Kpis\Period;
use App\Services\Kpis\Scope;
use App\Services\Kpis\TargetBook;
use App\Services\Kpis\TargetResolver;
use Carbon\CarbonImmutable;
use Tests\Support\KpiFactory;

/*
 * Las metas no se reparten parejo entre meses —10, 20 y 30 hacen 60—, pero dentro
 * del mes sí, por día natural: ventas trabaja los siete días (2026-09-26).
 */

beforeEach(function () {
    $this->resolver = app(TargetResolver::class);
    $this->venta = KpiFactory::saleType();
    $this->kpi = KpiFactory::kpi();
    $this->conteo = KpiFactory::aggregate($this->kpi, $this->venta, MeasureAggregation::Conteo);
    $this->general = Scope::general();
});

function periodo(KpiPeriodType $tipo, string $fecha): Period
{
    return Period::containing($tipo, CarbonImmutable::parse($fecha));
}

it('el trimestre de metas desiguales es la suma de sus meses', function () {
    KpiFactory::target($this->conteo, KpiPeriodType::Mes, '2026-07-01', 10);
    KpiFactory::target($this->conteo, KpiPeriodType::Mes, '2026-08-01', 20);
    KpiFactory::target($this->conteo, KpiPeriodType::Mes, '2026-09-01', 30);

    $t = periodo(KpiPeriodType::Trimestre, '2026-08-15');
    $agosto = periodo(KpiPeriodType::Mes, '2026-08-15');

    expect($this->resolver->for($this->conteo, $this->general, $t->start, $t->end))->toBe(60.0)
        ->and($this->resolver->for($this->conteo, $this->general, $agosto->start, $agosto->end))->toBe(20.0);
});

it('la meta capturada del trimestre manda sobre la suma de los meses', function () {
    KpiFactory::target($this->conteo, KpiPeriodType::Mes, '2026-07-01', 10);
    KpiFactory::target($this->conteo, KpiPeriodType::Trimestre, '2026-07-01', 75);

    $t = periodo(KpiPeriodType::Trimestre, '2026-07-01');

    expect($this->resolver->for($this->conteo, $this->general, $t->start, $t->end))->toBe(75.0);
});

it('un mes sin meta propia toma su parte del trimestre por días', function () {
    KpiFactory::target($this->conteo, KpiPeriodType::Trimestre, '2026-07-01', 92);

    // Jul-sep 2026 = 92 días: julio 31, agosto 31, septiembre 30.
    $septiembre = periodo(KpiPeriodType::Mes, '2026-09-10');

    expect($this->resolver->for($this->conteo, $this->general, $septiembre->start, $septiembre->end))->toBe(30.0);
});

it('reparte el mes por día natural y la semana que cruza dos meses toma de ambos', function () {
    KpiFactory::target($this->conteo, KpiPeriodType::Mes, '2026-09-01', 30);
    KpiFactory::target($this->conteo, KpiPeriodType::Mes, '2026-10-01', 62);

    $dia = periodo(KpiPeriodType::Dia, '2026-09-10');
    // Lunes 28 de septiembre a domingo 4 de octubre: 3 días a 1 y 4 días a 2.
    $semana = periodo(KpiPeriodType::Semana, '2026-09-30');

    expect($this->resolver->for($this->conteo, $this->general, $dia->start, $dia->end))->toBe(1.0)
        ->and($semana->start->toDateString())->toBe('2026-09-28')
        ->and($this->resolver->for($this->conteo, $this->general, $semana->start, $semana->end))->toBe(11.0);
});

it('una razón no se reparte: su meta es un nivel', function () {
    $leads = KpiFactory::leadType();
    $atendidos = KpiFactory::aggregate($this->kpi, $leads, MeasureAggregation::Conteo, attrs: ['slug' => 'leads']);
    $conversion = KpiFactory::formula($this->kpi, FormulaOperator::Razon, $this->conteo, $atendidos);
    KpiFactory::target($conversion, KpiPeriodType::Mes, '2026-09-01', 25);

    $dia = periodo(KpiPeriodType::Dia, '2026-09-10');

    expect($this->resolver->for($conversion, $this->general, $dia->start, $dia->end))->toBe(25.0);
});

it('separa las metas de todo ventas, del equipo y de cada persona', function () {
    $ana = User::factory()->create();
    $norte = KpiFactory::team('Norte');
    KpiFactory::target($this->conteo, KpiPeriodType::Mes, '2026-09-01', 100);
    KpiFactory::target($this->conteo, KpiPeriodType::Mes, '2026-09-01', 40, TargetScope::Equipo, $norte->id);
    KpiFactory::target($this->conteo, KpiPeriodType::Mes, '2026-09-01', 12, TargetScope::Persona, $ana->id);

    $mes = periodo(KpiPeriodType::Mes, '2026-09-01');

    expect($this->resolver->for($this->conteo, Scope::general(), $mes->start, $mes->end))->toBe(100.0)
        ->and($this->resolver->for($this->conteo, Scope::team($norte->id), $mes->start, $mes->end))->toBe(40.0)
        ->and($this->resolver->for($this->conteo, Scope::person($ana->id), $mes->start, $mes->end))->toBe(12.0);
});

it('cambiar una meta guarda la versión anterior con autor y motivo', function () {
    $admin = User::factory()->create();
    $libro = app(TargetBook::class);
    $celda = fn ($valor) => [['scope' => 'general', 'id' => null, 'period_type' => 'mes', 'period_start' => '2026-09-01', 'value' => $valor]];

    expect($libro->save($this->conteo, $celda(30), $admin, null))->toBe(1)
        ->and($libro->save($this->conteo, $celda(30), $admin, null))->toBe(0)
        ->and($libro->save($this->conteo, $celda(36), $admin, 'Campaña de fin de mes'))->toBe(1);

    $mes = periodo(KpiPeriodType::Mes, '2026-09-01');
    $versiones = $this->conteo->targets()->orderBy('id')->get();

    expect($this->resolver->for($this->conteo, Scope::general(), $mes->start, $mes->end))->toBe(36.0)
        ->and($versiones)->toHaveCount(2)
        ->and($versiones[0]->replaced_at)->not->toBeNull()
        ->and($versiones[1]->reason)->toBe('Campaña de fin de mes')
        ->and($versiones[1]->created_by)->toBe($admin->id);

    // Borrar la meta solo cierra la vigente.
    $libro->save($this->conteo, $celda(null), $admin, null);
    app(TargetResolver::class)->forget();

    expect(app(TargetResolver::class)->for($this->conteo, Scope::general(), $mes->start, $mes->end))->toBeNull()
        ->and($this->conteo->targets()->count())->toBe(2);
});
