<?php

declare(strict_types=1);

use App\Enums\RouteItemRole;
use App\Enums\RouteLayer;
use Tests\Support\DraftBuilder;

/*
 * Restricción 3 (docs/ARQUITECTURA.md §4.3) — LEADERSHIP_OVERLAP.
 *
 * Liderazgo para Ingenieros, Liderazgo para Líderes y Supervisores y Liderazgo
 * Efectivo se traslapan. Nunca pueden aparecer juntos *como recomendación*; el
 * documento anual sí puede listarlos como incluidos en la membresía.
 */

it('rechaza recomendar dos cursos de liderazgo a la vez', function () {
    $draft = DraftBuilder::enfocada()
        ->anchor('soft')
        ->course('liderazgo-para-ingenieros', RouteItemRole::Ancla)
        ->course('liderazgo-para-lideres-y-supervisores', RouteItemRole::Complementaria)
        ->build();

    $report = validateDraft($draft);

    expect($report)->toFailRule('LEADERSHIP_OVERLAP');
    expect($report->for('LEADERSHIP_OVERLAP')->details['conflicting'])->toHaveCount(2);
});

it('rechaza el traslape aunque el tercero entre como refuerzo', function () {
    $draft = DraftBuilder::anual()
        ->course('liderazgo-para-ingenieros', RouteItemRole::Ancla, month: 1)
        ->course('liderazgo-efectivo', RouteItemRole::Refuerzo, month: 9)
        ->build();

    expect(validateDraft($draft))->toFailRule('LEADERSHIP_OVERLAP');
});

it('admite un solo curso de liderazgo como recomendación', function () {
    $draft = DraftBuilder::enfocada()
        ->anchor('soft')
        ->course('liderazgo-para-lideres-y-supervisores', RouteItemRole::Ancla)
        ->course('comunicacion-efectiva')
        ->build();

    expect(validateDraft($draft))->toPassRule('LEADERSHIP_OVERLAP');
});

it('deja que el documento anual liste los tres como incluidos y recomiende uno', function () {
    $draft = DraftBuilder::anual()
        ->course('liderazgo-para-lideres-y-supervisores', RouteItemRole::Ancla, month: 2)
        ->courses(
            ['liderazgo-para-ingenieros', 'liderazgo-para-lideres-y-supervisores', 'liderazgo-efectivo'],
            RouteItemRole::Incluido,
            RouteLayer::Inventario,
        )
        ->build();

    expect(validateDraft($draft))->toPassRule('LEADERSHIP_OVERLAP');
});

it('admite el inventario completo del catálogo junto a una recomendación', function () {
    $draft = DraftBuilder::anual()
        ->course('liderazgo-para-ingenieros', RouteItemRole::Ancla, month: 1)
        ->fullInventory()
        ->build();

    expect(validateDraft($draft))->toPassRule('LEADERSHIP_OVERLAP');
});
