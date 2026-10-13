<?php

declare(strict_types=1);

use App\Enums\RouteItemRole;
use Tests\Support\DraftBuilder;

/*
 * Restricción 6 (docs/ARQUITECTURA.md §4.6) — ANNUAL_REQUIRES_MEMBERSHIP.
 *
 * El documento anual exige nombre completo, ocupación, inicio y fin de vigencia.
 * Sin ellos no se genera: es el documento que el miembro conserva.
 */

$membership = [
    'full_name' => 'María Fernanda Ruiz Delgado',
    'occupation' => 'Supervisora de producción',
    'starts_on' => '2026-09-15',
    'ends_on' => '2027-09-14',
];

it('rechaza la ruta anual sin datos de membresía', function () {
    $draft = DraftBuilder::anual()
        ->course('lean-manufacturing', RouteItemRole::Ancla, month: 1)
        ->build();

    $report = validateDraft($draft);

    expect($report)->toFailRule('ANNUAL_REQUIRES_MEMBERSHIP');
    expect($report->for('ANNUAL_REQUIRES_MEMBERSHIP')->details['missing'])
        ->toBe(['full_name', 'occupation', 'starts_on', 'ends_on']);
});

it('rechaza la ruta anual con la vigencia incompleta', function () use ($membership) {
    $draft = DraftBuilder::anual()
        ->course('lean-manufacturing', RouteItemRole::Ancla, month: 1)
        ->membership([...$membership, 'ends_on' => null])
        ->build();

    $report = validateDraft($draft);

    expect($report)->toFailRule('ANNUAL_REQUIRES_MEMBERSHIP');
    expect($report->for('ANNUAL_REQUIRES_MEMBERSHIP')->details['missing'])->toBe(['ends_on']);
});

it('rechaza la ocupación en blanco', function () use ($membership) {
    $draft = DraftBuilder::anual()
        ->course('lean-manufacturing', RouteItemRole::Ancla, month: 1)
        ->membership([...$membership, 'occupation' => '   '])
        ->build();

    expect(validateDraft($draft))->toFailRule('ANNUAL_REQUIRES_MEMBERSHIP');
});

it('acepta la ruta anual con los cuatro datos', function () use ($membership) {
    $draft = DraftBuilder::anual()
        ->course('lean-manufacturing', RouteItemRole::Ancla, month: 1)
        ->membership($membership)
        ->build();

    expect(validateDraft($draft))->toPassRule('ANNUAL_REQUIRES_MEMBERSHIP');
});

it('no le exige membresía a la ruta enfocada', function () {
    $draft = DraftBuilder::enfocada()
        ->course('lean-manufacturing', RouteItemRole::Ancla)
        ->build();

    expect(validateDraft($draft))->toNotEvaluateRule('ANNUAL_REQUIRES_MEMBERSHIP');
});
