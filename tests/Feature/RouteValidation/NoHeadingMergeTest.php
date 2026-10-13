<?php

declare(strict_types=1);

use App\Enums\RouteItemRole;
use Tests\Support\DraftBuilder;

/*
 * Restricción 8 (docs/ARQUITECTURA.md §4.8) — NO_HEADING_MERGE.
 *
 * Cada especialización se presenta bajo su propio título. Dos especializaciones
 * pueden compartir un mes, jamás un encabezado.
 */

it('rechaza un encabezado que revuelve dos especializaciones', function () {
    $draft = DraftBuilder::anual()
        ->course('gestion-de-inventarios', RouteItemRole::Refuerzo, month: 7)
        ->course('kaizen-mejora-continua', RouteItemRole::Complementaria, month: 7)
        ->heading('Inventarios y Mejora Continua', ['suministros', 'kaizen'], month: 7)
        ->build();

    $report = validateDraft($draft);

    expect($report)->toFailRule('NO_HEADING_MERGE');
    expect($report->for('NO_HEADING_MERGE')->details['violations'][0]['specializations'])
        ->toEqualCanonicalizing(['Cadena de Suministros', 'Kaizen y Mejora Continua']);
});

it('acepta dos especializaciones en un mes, cada una bajo su título', function () {
    $draft = DraftBuilder::anual()
        ->course('gestion-de-inventarios', RouteItemRole::Refuerzo, month: 7)
        ->course('kaizen-mejora-continua', RouteItemRole::Complementaria, month: 7)
        ->heading('Cadena de Suministros', ['suministros'], month: 7)
        ->heading('Kaizen y Mejora Continua', ['kaizen'], month: 7)
        ->build();

    expect(validateDraft($draft))->toPassRule('NO_HEADING_MERGE');
});

it('se omite cuando el borrador todavía no declara encabezados', function () {
    $draft = DraftBuilder::enfocada()
        ->course('lean-manufacturing', RouteItemRole::Ancla)
        ->build();

    expect(validateDraft($draft))->toSkipRule('NO_HEADING_MERGE');
});
