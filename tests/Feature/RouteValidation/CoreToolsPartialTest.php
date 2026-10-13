<?php

declare(strict_types=1);

use App\Enums\RouteItemRole;
use App\Models\CatalogRule;
use App\Models\Specialization;
use App\Services\RouteValidation\Data\RouteDraft;
use Tests\Support\DraftBuilder;

/*
 * Restricción 4 (docs/ARQUITECTURA.md §4.4) — CORE_TOOLS_PARTIAL.
 *
 * Core Tools publica AMEF, PPAP, SPC y Plan de Control. Faltan APQP y MSA, dos de
 * las cinco herramientas AIAG. Prometerla completa a un lead de cadena de
 * suministro automotriz destruye la credibilidad del asesor.
 */

function coreToolsDraft(string ...$copy): RouteDraft
{
    return DraftBuilder::enfocada()
        ->anchor('coretools')
        ->course('amef', RouteItemRole::Ancla)
        ->course('ppap')
        ->course('spc')
        ->course('plan-de-control')
        ->copy(...$copy)
        ->build();
}

it('rechaza prometer Core Tools completo', function () {
    $report = validateDraft(coreToolsDraft(
        'Con esta ruta te llevas Core Tools completo para el sector automotriz.',
    ));

    expect($report)->toFailRule('CORE_TOOLS_PARTIAL');
    expect($report->for('CORE_TOOLS_PARTIAL')->details['violations'][0])
        ->toMatchArray(['specialization' => 'Core Tools', 'missing_components' => ['APQP', 'MSA']]);
});

it('rechaza prometer las cinco herramientas AIAG', function () {
    $report = validateDraft(coreToolsDraft(
        'Domina las cinco herramientas que pide la industria automotriz.',
    ));

    expect($report)->toFailRule('CORE_TOOLS_PARTIAL');
});

it('admite decir «cuatro de las cinco herramientas AIAG», que es lo cierto', function () {
    expect(validateDraft(coreToolsDraft(
        'Cuatro de las cinco herramientas AIAG: AMEF, PPAP, SPC y Plan de Control.',
    )))->toPassRule('CORE_TOOLS_PARTIAL');
});

it('rechaza la variante «todas las herramientas Core Tools»', function () {
    expect(validateDraft(coreToolsDraft('Cubre todas las herramientas Core Tools.')))
        ->toFailRule('CORE_TOOLS_PARTIAL');
});

it('admite enumerar las herramientas sin afirmar completitud', function () {
    expect(validateDraft(coreToolsDraft(
        'Incluye AMEF, PPAP, Control Estadístico de Procesos y Plan de Control.',
    )))->toPassRule('CORE_TOOLS_PARTIAL');
});

it('se omite cuando el borrador todavía no trae copy ensamblado', function () {
    expect(validateDraft(coreToolsDraft()))->toSkipRule('CORE_TOOLS_PARTIAL');
});

it('protege a cualquier especialización marcada como parcial, no solo a Core Tools', function () {
    Specialization::where('slug', 'lean')->sole()->update([
        'completeness_status' => 'partial',
        'missing_components' => ['Jidoka'],
    ]);

    CatalogRule::where('code', 'CORE_TOOLS_PARTIAL')->sole()->specializations()->detach();

    $draft = DraftBuilder::enfocada()
        ->anchor('lean')
        ->course('lean-manufacturing', RouteItemRole::Ancla)
        ->copy('Te llevas Lean Manufacturing completo.')
        ->build();

    expect(validateDraft($draft))->toFailRule('CORE_TOOLS_PARTIAL');
});
