<?php

declare(strict_types=1);

use App\Enums\RouteItemRole;
use Tests\Support\DraftBuilder;

/*
 * Restricción 2 (docs/ARQUITECTURA.md §4.2) — MODULE_DOUBLE_COUNT.
 *
 * Los tres paquetes «Completo» contienen a sus propios niveles: 64 cursos son 55
 * programas de contenido distintos. El conteo debe ser correcto y auditable si el
 * lead se pone a sumar.
 */

it('acepta el conteo cuando cursos y programas cuadran', function () {
    $draft = DraftBuilder::enfocada()
        ->course('excel-completo', RouteItemRole::Ancla)
        ->course('excel-basico')
        ->course('excel-intermedio')
        ->declaring(courses: 3, contentUnits: 1)
        ->build();

    expect(validateDraft($draft))->toPassRule('MODULE_DOUBLE_COUNT');
});

it('rechaza contar cada nivel como programa aparte', function () {
    $draft = DraftBuilder::enfocada()
        ->course('excel-completo', RouteItemRole::Ancla)
        ->course('excel-basico')
        ->course('excel-intermedio')
        ->declaring(courses: 3, contentUnits: 3)
        ->build();

    $report = validateDraft($draft);

    expect($report)->toFailRule('MODULE_DOUBLE_COUNT');
    expect($report->for('MODULE_DOUBLE_COUNT')->details)
        ->toMatchArray([
            'declared' => ['course_count' => 3, 'content_unit_count' => 3],
            'computed' => ['course_count' => 3, 'content_unit_count' => 1],
            'mismatched_fields' => ['content_unit_count'],
        ]);
});

it('reproduce las cifras que el documento anual imprime: 64 cursos, 55 programas', function () {
    $draft = DraftBuilder::anual()
        ->fullInventory()
        ->declaring(courses: 64, contentUnits: 55)
        ->build();

    expect(validateDraft($draft))->toPassRule('MODULE_DOUBLE_COUNT');
});

it('rechaza declarar 64 programas', function () {
    $draft = DraftBuilder::anual()
        ->fullInventory()
        ->declaring(courses: 64, contentUnits: 64)
        ->build();

    expect(validateDraft($draft))->toFailRule('MODULE_DOUBLE_COUNT');
});

it('no cuenta los renglones marcados fuera del total', function () {
    $draft = DraftBuilder::enfocada()
        ->course('lean-manufacturing', RouteItemRole::Ancla)
        ->course('smed', countsTowardTotal: false)
        ->declaring(courses: 1, contentUnits: 1)
        ->build();

    expect(validateDraft($draft))->toPassRule('MODULE_DOUBLE_COUNT');
});

it('se omite cuando el borrador todavía no declara cifras', function () {
    $draft = DraftBuilder::enfocada()
        ->course('lean-manufacturing', RouteItemRole::Ancla)
        ->build();

    expect(validateDraft($draft))->toSkipRule('MODULE_DOUBLE_COUNT');
});
