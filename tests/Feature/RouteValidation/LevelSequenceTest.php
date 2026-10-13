<?php

declare(strict_types=1);

use App\Enums\RouteItemRole;
use App\Enums\RouteLayer;
use Tests\Support\DraftBuilder;

/*
 * Restricción 7 (docs/ARQUITECTURA.md §4.7) — LEVEL_SEQUENCE.
 *
 * En el itinerario, Básico nunca cae después de Intermedio o Avanzado. El orden
 * sale de courses.level y course_prerequisites, no de una lista escrita a mano.
 */

it('rechaza Excel Básico después de Excel Avanzado', function () {
    $draft = DraftBuilder::anual()
        ->course('excel-avanzado', RouteItemRole::Ancla, month: 2)
        ->course('excel-basico', month: 6)
        ->build();

    $report = validateDraft($draft);

    expect($report)->toFailRule('LEVEL_SEQUENCE');
    expect($report->for('LEVEL_SEQUENCE')->details['violations'][0])
        ->toMatchArray([
            'kind' => 'level_order',
            'expected_first' => 'Microsoft Excel Básico',
            'placed_first' => 'Microsoft Excel Avanzado',
        ]);
});

it('rechaza el desorden dentro de un mismo mes', function () {
    $draft = DraftBuilder::anual()
        ->course('autocad-intermedio', RouteItemRole::Ancla, month: 3, position: 1)
        ->course('autocad-basico', month: 3, position: 2)
        ->build();

    expect(validateDraft($draft))->toFailRule('LEVEL_SEQUENCE');
});

it('acepta la escalera en orden', function () {
    $draft = DraftBuilder::anual()
        ->course('autocad-basico', RouteItemRole::Ancla, month: 1)
        ->course('autocad-intermedio', month: 3)
        ->course('autocad-avanzado', month: 5)
        ->build();

    expect(validateDraft($draft))->toPassRule('LEVEL_SEQUENCE');
});

it('no confunde niveles de familias distintas', function () {
    // Excel Básico después de AutoCAD Avanzado es legítimo: son unidades distintas.
    $draft = DraftBuilder::anual()
        ->course('autocad-avanzado', RouteItemRole::Ancla, month: 2)
        ->course('excel-basico', month: 8)
        ->build();

    expect(validateDraft($draft))->toPassRule('LEVEL_SEQUENCE');
});

it('no trata un paquete «Completo» como escalón de la escalera', function () {
    $draft = DraftBuilder::anual()
        ->course('solidworks-completo', RouteItemRole::Ancla, month: 1)
        ->course('solidworks-basico', month: 4)
        ->build();

    expect(validateDraft($draft))->toPassRule('LEVEL_SEQUENCE');
});

it('exige que un prerrequisito duro presente en la ruta vaya antes', function () {
    $draft = DraftBuilder::anual()
        ->course('solidworks-avanzado', RouteItemRole::Ancla, month: 2)
        ->course('solidworks-intermedio', month: 7)
        ->build();

    $kinds = array_column(
        validateDraft($draft)->for('LEVEL_SEQUENCE')->details['violations'],
        'kind',
    );

    expect($kinds)->toContain('prerequisite_order');
});

it('no exige que el prerrequisito esté en la ruta', function () {
    // El lead pudo llegar sabiendo AutoCAD Básico; omitirlo no es una falta.
    $draft = DraftBuilder::anual()
        ->course('autocad-intermedio', RouteItemRole::Ancla, month: 1)
        ->course('autocad-avanzado', month: 4)
        ->build();

    expect(validateDraft($draft))->toPassRule('LEVEL_SEQUENCE');
});

it('no evalúa el inventario de la membresía, que no lleva orden', function () {
    $draft = DraftBuilder::anual()
        ->course('lean-manufacturing', RouteItemRole::Ancla, month: 1)
        ->courses(['excel-avanzado', 'excel-basico'], RouteItemRole::Incluido, RouteLayer::Inventario)
        ->build();

    expect(validateDraft($draft))->toPassRule('LEVEL_SEQUENCE');
});
