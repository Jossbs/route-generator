<?php

declare(strict_types=1);

use App\Enums\RouteItemRole;
use App\Enums\ValidationResult;
use App\Models\CatalogRule;
use App\Models\CatalogVersion;
use App\Models\DocumentType;
use App\Models\Lead;
use App\Models\LearningRoute;
use App\Services\RouteValidation\Exceptions\UnknownRuleTypeException;
use App\Services\RouteValidation\RouteValidator;
use Database\Seeders\CatalogVersionSeeder;
use Database\Seeders\DocumentTypeSeeder;
use Tests\Support\DraftBuilder;

/*
 * El orquestador: qué reglas carga, cuándo bloquea y qué deja asentado.
 * El flujo es obligatorio — el generador propone, el validador aprueba o rechaza,
 * y solo entonces se renderiza.
 */

it('evalúa toda regla vigente que aplique a la ruta anual', function () {
    $draft = DraftBuilder::anual()
        ->course('lean-manufacturing', RouteItemRole::Ancla, month: 1)
        ->build();

    $evaluadas = collect(validateDraft($draft)->evaluations)->pluck('ruleCode');

    // Ninguna regla vigente puede quedarse sin evaluar. Se compara contra lo que
    // hay sembrado y no contra una lista escrita a mano, porque una lista a mano
    // se olvida de actualizar y la regla nueva deja de vigilar sin que nadie note.
    $esperadas = CatalogRule::query()
        ->inEffect()
        ->get()
        ->filter(fn (CatalogRule $rule): bool => $rule->appliesToDocumentType(DocumentTypeSeeder::ANUAL))
        ->pluck('code');

    expect($evaluadas->sort()->values()->all())->toBe($esperadas->sort()->values()->all());
    expect($evaluadas)->toContain('ANNUAL_REQUIRES_MEMBERSHIP');
});

it('deja fuera de la ruta enfocada la regla que solo aplica al anual', function () {
    $draft = DraftBuilder::enfocada()
        ->course('lean-manufacturing', RouteItemRole::Ancla)
        ->build();

    $evaluadas = collect(validateDraft($draft)->evaluations)->pluck('ruleCode');

    expect($evaluadas)->not->toContain('ANNUAL_REQUIRES_MEMBERSHIP');
    // Las que aplican al gancho, no «todas menos una»: el anual ya tiene más de
    // una regla propia (ANNUAL_FITS_PACE, 2026-09-22).
    expect($evaluadas)->toHaveCount(reglasDelGancho());
});

it('no evalúa una regla desactivada', function () {
    CatalogRule::where('code', 'SUPPLY_CHAIN_ROLE')->update(['is_active' => false]);

    $draft = DraftBuilder::enfocada()
        ->course('gestion-de-inventarios', RouteItemRole::Ancla)
        ->build();

    expect(validateDraft($draft))->toNotEvaluateRule('SUPPLY_CHAIN_ROLE');
});

it('no evalúa una regla que aún no entra en vigor', function () {
    CatalogRule::where('code', 'SUPPLY_CHAIN_ROLE')->update(['effective_from' => now()->addMonth()]);

    $draft = DraftBuilder::enfocada()
        ->course('gestion-de-inventarios', RouteItemRole::Ancla)
        ->build();

    expect(validateDraft($draft))->toNotEvaluateRule('SUPPLY_CHAIN_ROLE');
});

it('bloquea el paso al render cuando falla una regla bloqueante', function () {
    $draft = DraftBuilder::enfocada()
        ->course('gestion-de-inventarios', RouteItemRole::Ancla)
        ->build();

    $report = validateDraft($draft);

    expect($report->passes())->toBeFalse();
    expect($report->blockingMessages())->toHaveCount(1);
    expect($report->blockingMessages()[0])->toContain('Cadena de Suministros');
});

it('deja pasar un fallo marcado como advertencia', function () {
    CatalogRule::where('code', 'SUPPLY_CHAIN_ROLE')->update(['severity' => 'warning']);

    $draft = DraftBuilder::enfocada()
        ->course('gestion-de-inventarios', RouteItemRole::Ancla)
        ->build();

    $report = validateDraft($draft);

    expect($report)->toFailRule('SUPPLY_CHAIN_ROLE');
    expect($report->passes())->toBeTrue();
    expect($report->warnings()->pluck('ruleCode'))->toContain('SUPPLY_CHAIN_ROLE');
});

it('falla ruidosamente ante un tipo de regla sin intérprete', function () {
    // Saltarse en silencio una restricción bloqueante es peor que reventar.
    CatalogRule::where('code', 'SUPPLY_CHAIN_ROLE')->update(['type' => 'regla_del_futuro']);

    $draft = DraftBuilder::enfocada()
        ->course('lean-manufacturing', RouteItemRole::Ancla)
        ->build();

    validateDraft($draft);
})->throws(UnknownRuleTypeException::class, 'SUPPLY_CHAIN_ROLE');

it('asienta cada veredicto en la bitácora de validaciones', function () {
    $route = makeRoute();

    $draft = DraftBuilder::enfocada()
        ->course('gestion-de-inventarios', RouteItemRole::Ancla)
        ->build();

    $report = validateDraft($draft);
    app(RouteValidator::class)->record($route, $report);

    expect($route->validations()->count())->toBe(count($report->evaluations));

    $supplyChain = $route->validations()->where('rule_code', 'SUPPLY_CHAIN_ROLE')->sole();

    expect($supplyChain->result)->toBe(ValidationResult::Fail);
    expect($supplyChain->details['violations'][0]['specialization'])->toBe('Cadena de Suministros');
});

it('reemplaza la bitácora al revalidar en lugar de acumularla', function () {
    $route = makeRoute();
    $validator = app(RouteValidator::class);

    $rejected = DraftBuilder::enfocada()->course('gestion-de-inventarios', RouteItemRole::Ancla)->build();
    $validator->record($route, $validator->validate($rejected));

    $fixed = DraftBuilder::enfocada()->course('lean-manufacturing', RouteItemRole::Ancla)->build();
    $validator->record($route, $validator->validate($fixed));

    // La bitácora se reemplaza, no se acumula: tras revalidar queda un solo
    // veredicto por regla y ninguno del intento anterior.
    expect($route->validations()->count())->toBe(reglasDelGancho());
    expect($route->validations()->where('rule_code', 'SUPPLY_CHAIN_ROLE')->sole()->result)
        ->toBe(ValidationResult::Pass);
});

function makeRoute(): LearningRoute
{
    return LearningRoute::create([
        'lead_id' => Lead::create(['name' => 'Lead de prueba', 'source' => 'formulario'])->id,
        'document_type_id' => DocumentType::where('slug', DocumentTypeSeeder::ENFOCADA)->sole()->id,
        'catalog_version_id' => CatalogVersion::where('label', CatalogVersionSeeder::V1_LABEL)->sole()->id,
        'status' => 'draft',
    ]);
}

/** Cuántas reglas vigentes evalúa la ruta enfocada. */
function reglasDelGancho(): int
{
    return CatalogRule::query()
        ->inEffect()
        ->get()
        ->filter(fn (CatalogRule $rule): bool => $rule->appliesToDocumentType(DocumentTypeSeeder::ENFOCADA))
        ->count();
}
