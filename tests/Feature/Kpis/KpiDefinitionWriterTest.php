<?php

declare(strict_types=1);

use App\Enums\Kpis\FormulaOperator;
use App\Enums\Kpis\MeasureAggregation;
use App\Models\Kpi;
use App\Models\KpiMeasure;
use App\Services\Kpis\Settings\EventTypeWriter;
use App\Services\Kpis\Settings\KpiDefinitionWriter;
use Illuminate\Validation\ValidationException;
use Tests\Support\KpiFactory;

/*
 * Un KPI nuevo es un alta, no un deploy (2026-09-26). Lo que aquí se valida es
 * que toda medida guardada se pueda calcular.
 */

beforeEach(function () {
    $this->writer = app(KpiDefinitionWriter::class);
    $this->venta = KpiFactory::saleType();
});

function medida(array $extra): array
{
    return ['name' => 'Medida', 'unit' => 'piezas', 'kind' => 'agregado', 'filters' => [], ...$extra];
}

it('no guarda filtros que provocarían un error de conversión numérica en el tablero', function (string $field, mixed $value) {
    expect(fn () => $this->writer->save(null, [
        'name' => 'Filtro inválido',
        'measures' => [medida([
            'sales_event_type_id' => $this->venta->id, 'aggregation' => 'conteo',
            'filters' => [['field' => $field, 'op' => '>', 'value' => $value]],
        ])],
    ]))->toThrow(ValidationException::class);

    expect(Kpi::count())->toBe(0);
})->with([['plan', 2], ['importe', 'sin número'], ['importe', ['invalido']]]);

it('no transforma una medida ajena en una medida nueva de otro KPI', function () {
    $ajeno = KpiFactory::aggregate(KpiFactory::kpi(), $this->venta, MeasureAggregation::Conteo);

    expect(fn () => $this->writer->save(null, [
        'name' => 'Otro KPI',
        'measures' => [medida(['id' => $ajeno->id, 'sales_event_type_id' => $this->venta->id, 'aggregation' => 'conteo'])],
    ]))->toThrow(ValidationException::class);

    expect(KpiMeasure::count())->toBe(1);
});

it('no acepta un campo que pertenece a otro tipo de evento', function () {
    $campoAjeno = $this->venta->fields->first();

    expect(fn () => app(EventTypeWriter::class)->save(null, [
        'name' => 'Otro evento',
        'fields' => [['id' => $campoAjeno->id, 'label' => 'Importe', 'data_type' => 'dinero']],
    ]))->toThrow(ValidationException::class);
});

it('crea un KPI con varias medidas, cada una con su meta', function () {
    $kpi = $this->writer->save(null, [
        'name' => 'Promos vendidas',
        'measures' => [
            medida(['name' => 'Piezas', 'sales_event_type_id' => $this->venta->id, 'aggregation' => 'conteo']),
            medida(['name' => 'Dinero', 'unit' => 'dinero', 'sales_event_type_id' => $this->venta->id, 'aggregation' => 'suma', 'field_key' => 'importe']),
        ],
    ]);

    expect($kpi->slug)->toBe('promos-vendidas')
        ->and($kpi->measures->pluck('slug')->all())->toBe(['piezas', 'dinero'])
        ->and($kpi->measures->every(fn (KpiMeasure $m) => $m->has_target))->toBeTrue();
});

it('no deja sumar un campo que no es número', function () {
    expect(fn () => $this->writer->save(null, [
        'name' => 'Raro',
        'measures' => [medida(['sales_event_type_id' => $this->venta->id, 'aggregation' => 'suma', 'field_key' => 'plan'])],
    ]))->toThrow(ValidationException::class);
});

it('no deja filtrar por un campo que el tipo no tiene', function () {
    expect(fn () => $this->writer->save(null, [
        'name' => 'Raro',
        'measures' => [medida(['sales_event_type_id' => $this->venta->id, 'aggregation' => 'conteo',
            'filters' => [['field' => 'color', 'op' => '=', 'value' => 'rojo']]])],
    ]))->toThrow(ValidationException::class);
});

it('arma una conversión con medidas ya guardadas y detecta ciclos', function () {
    $leads = KpiFactory::leadType();
    $kpi = $this->writer->save(null, [
        'name' => 'Conversión',
        'measures' => [
            medida(['name' => 'Ventas', 'sales_event_type_id' => $this->venta->id, 'aggregation' => 'conteo']),
            medida(['name' => 'Leads', 'sales_event_type_id' => $leads->id, 'aggregation' => 'conteo']),
        ],
    ]);
    [$ventas, $atendidos] = $kpi->measures->all();

    $kpi = $this->writer->save($kpi, ['name' => 'Conversión', 'measures' => [
        ['id' => $ventas->id, ...medida(['name' => 'Ventas', 'sales_event_type_id' => $this->venta->id, 'aggregation' => 'conteo'])],
        ['id' => $atendidos->id, ...medida(['name' => 'Leads', 'sales_event_type_id' => $leads->id, 'aggregation' => 'conteo'])],
        medida(['name' => 'Tasa', 'unit' => 'porcentaje', 'kind' => 'formula', 'formula_operator' => 'razon',
            'left_measure_id' => $ventas->id, 'right_measure_id' => $atendidos->id]),
    ]]);
    $tasa = $kpi->measures->firstWhere('name', 'Tasa');

    expect($tasa->isSummable())->toBeFalse();

    // Otra fórmula que usa la tasa, y luego la tasa apuntando a esa: ciclo.
    $otro = $this->writer->save(null, ['name' => 'Otro', 'measures' => [
        medida(['name' => 'Doble', 'kind' => 'formula', 'formula_operator' => 'suma', 'left_measure_id' => $tasa->id, 'right_measure_id' => $tasa->id]),
    ]]);

    expect(fn () => $this->writer->save($kpi->fresh(), ['name' => 'Conversión', 'measures' => [
        ['id' => $ventas->id, ...medida(['name' => 'Ventas', 'sales_event_type_id' => $this->venta->id, 'aggregation' => 'conteo'])],
        ['id' => $atendidos->id, ...medida(['name' => 'Leads', 'sales_event_type_id' => $leads->id, 'aggregation' => 'conteo'])],
        ['id' => $tasa->id, ...medida(['name' => 'Tasa', 'kind' => 'formula', 'formula_operator' => 'razon',
            'left_measure_id' => $otro->measures->first()->id, 'right_measure_id' => $atendidos->id])],
    ]]))->toThrow(ValidationException::class);
});

it('no deja quitar una medida que una fórmula usa', function () {
    $kpi = KpiFactory::kpi('Base');
    $a = KpiFactory::aggregate($kpi, $this->venta, MeasureAggregation::Conteo);
    KpiFactory::formula(KpiFactory::kpi('Deriva'), FormulaOperator::Suma, $a, $a);

    expect(fn () => $this->writer->save($kpi, ['name' => 'Base', 'measures' => [
        medida(['name' => 'Nueva', 'sales_event_type_id' => $this->venta->id, 'aggregation' => 'conteo']),
    ]]))->toThrow(ValidationException::class);

    expect(Kpi::query()->where('slug', 'base')->first()->measures)->toHaveCount(1);
});

it('crea tipos de evento con campos y no deja quitar un campo que una medida lee', function () {
    $tipos = app(EventTypeWriter::class);
    $cobro = $tipos->save(null, ['name' => 'Cobro', 'fields' => [
        ['label' => 'Monto cobrado', 'data_type' => 'dinero', 'is_required' => true],
        ['label' => 'Método', 'data_type' => 'opcion', 'options' => [['value' => 'efectivo', 'label' => 'Efectivo']]],
    ]]);

    expect($cobro->fields->pluck('key')->all())->toBe(['monto_cobrado', 'metodo']);

    $this->writer->save(null, ['name' => 'Cobranza', 'measures' => [
        medida(['unit' => 'dinero', 'sales_event_type_id' => $cobro->id, 'aggregation' => 'suma', 'field_key' => 'monto_cobrado']),
    ]]);

    expect(fn () => $tipos->save($cobro, ['name' => 'Cobro', 'fields' => [
        ['id' => $cobro->fields[1]->id, 'label' => 'Método', 'data_type' => 'opcion', 'options' => [['value' => 'efectivo', 'label' => 'Efectivo']]],
    ]]))->toThrow(ValidationException::class);
});
