<?php

declare(strict_types=1);

use App\Enums\RouteItemRole;
use App\Enums\RouteLayer;
use App\Models\Specialization;
use Tests\Support\DraftBuilder;

/*
 * Restricción 1 (docs/ARQUITECTURA.md §4.1) — SUPPLY_CHAIN_ROLE.
 *
 * Cadena de Suministros tiene un solo curso. Conserva identidad y encabezado
 * propio; lo prohibido es que ocupe rol de ancla o de complementaria, donde
 * quedaría un bloque de un curso junto a otros de ocho o dieciséis.
 */

it('rechaza Cadena de Suministros como ancla de la ruta enfocada', function () {
    $draft = DraftBuilder::enfocada()
        ->anchor('suministros')
        ->course('gestion-de-inventarios', RouteItemRole::Ancla)
        ->build();

    expect(validateDraft($draft))->toFailRule('SUPPLY_CHAIN_ROLE');
});

it('rechaza Cadena de Suministros como complementaria', function () {
    $draft = DraftBuilder::enfocada()
        ->anchor('lean')
        ->course('lean-manufacturing', RouteItemRole::Ancla)
        ->course('gestion-de-inventarios', RouteItemRole::Complementaria)
        ->build();

    $report = validateDraft($draft);

    expect($report)->toFailRule('SUPPLY_CHAIN_ROLE');
    expect($report->for('SUPPLY_CHAIN_ROLE')->details['violations'][0])
        ->toMatchArray(['specialization' => 'Cadena de Suministros', 'presentation_role' => 'complementaria']);
});

it('admite Cadena de Suministros con bloque propio en el itinerario anual', function () {
    $draft = DraftBuilder::anual()
        ->anchor('lean')
        ->course('lean-manufacturing', RouteItemRole::Ancla, month: 1)
        ->course('gestion-de-inventarios', RouteItemRole::Refuerzo, month: 7)
        ->build();

    expect(validateDraft($draft))->toPassRule('SUPPLY_CHAIN_ROLE');
});

it('admite Cadena de Suministros en el inventario de la membresía', function () {
    $draft = DraftBuilder::anual()
        ->course('lean-manufacturing', RouteItemRole::Ancla, month: 1)
        ->course('gestion-de-inventarios', RouteItemRole::Incluido, RouteLayer::Inventario)
        ->build();

    expect(validateDraft($draft))->toPassRule('SUPPLY_CHAIN_ROLE');
});

it('no estorba a las demás especializaciones como ancla', function () {
    $draft = DraftBuilder::enfocada()
        ->anchor('coretools')
        ->course('amef', RouteItemRole::Ancla)
        ->course('metodologia-5s', RouteItemRole::Complementaria)
        ->build();

    expect(validateDraft($draft))->toPassRule('SUPPLY_CHAIN_ROLE');
});

it('resuelve la restricción como dato y no como código', function () {
    // Si mañana Cadena de Suministros creciera, bastaría con cambiar la ficha.
    $suministros = Specialization::where('slug', 'suministros')->sole();
    $suministros->update(['allowed_roles' => ['ancla' => true, 'complementaria' => true, 'bloque_itinerario' => true, 'inventario' => true]]);

    $draft = DraftBuilder::enfocada()
        ->course('gestion-de-inventarios', RouteItemRole::Ancla)
        ->build();

    expect(validateDraft($draft))->toPassRule('SUPPLY_CHAIN_ROLE');
});
