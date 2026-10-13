<?php

declare(strict_types=1);

use App\Enums\RouteStatus;
use App\Enums\UsageEventType;
use App\Enums\ValidationResult;
use App\Models\CatalogRule;
use App\Models\CatalogVersion;
use App\Models\LearningRoute;
use App\Models\RouteItem;
use App\Services\RouteComposition\ComposerRegistry;
use App\Services\RouteGeneration\Data\RouteInput;
use App\Services\RouteGeneration\RouteGenerator;
use Tests\Support\CompositionFactory;

/*
 * El flujo completo: el compositor propone, el validador aprueba o rechaza, y solo
 * entonces la ruta queda en condiciones de renderizarse.
 *
 * Una ruta rechazada también se persiste. El asesor necesita ver por qué, y la
 * bitácora necesita poder demostrar después que la restricción se evaluó.
 */

function generate(string $anchorSlug, bool $anual = false)
{
    $request = $anual
        ? CompositionFactory::anual($anchorSlug)
        : CompositionFactory::enfocada($anchorSlug);

    return app(RouteGenerator::class)->generate(RouteInput::free($request));
}

it('deja la ruta validada y lista para renderizar', function () {
    $result = generate('coretools');

    expect($result->approved())->toBeTrue();
    expect($result->route->status)->toBe(RouteStatus::Validated);
    expect($result->reasons())->toBeEmpty();
});

it('persiste los renglones para poder consultarlos sin abrir el JSON', function () {
    $result = generate('lean');

    expect($result->route->items()->count())->toBe(count($result->route->snapshot['ruta']['cursos']));
    expect(RouteItem::where('route_id', $result->route->id)->count())->toBeGreaterThan(0);
});

it('guarda el snapshot con el que el documento se puede reproducir después', function () {
    $result = generate('sixsigma');
    $snapshot = $result->route->snapshot;

    // El payload tiene que bastarse solo: si el catálogo cambia en tres meses, el
    // documento que recibió el lead se reproduce desde aquí sin volver a consultarlo.
    expect($snapshot)->toHaveKeys(['schema_version', 'tipo_documento', 'catalogo_version', 'generado_en', 'ruta']);
    expect($snapshot['tipo_documento'])->toBe('ruta-enfocada');
    expect($snapshot['catalogo_version'])->toBe(CatalogVersion::current()->label);
    expect($snapshot['ruta'])->toHaveKeys(['encabezados', 'cursos', 'totales']);
    expect($snapshot['ruta']['cursos'])->not->toBeEmpty();
});

it('ata cada ruta a la versión de catálogo con la que se armó', function () {
    $result = generate('kaizen');

    expect($result->route->catalog_version_id)->toBe(CatalogVersion::current()->id);
});

it('emite token de acceso solo para el documento que se entrega por enlace', function () {
    expect(generate('lean')->route->access_token)->not->toBeNull();
    expect(generate('lean', anual: true)->route->access_token)->toBeNull();
});

it('asienta cada veredicto en la bitácora, no solo los que fallaron', function () {
    $result = generate('coretools');

    $validations = $result->route->validations;

    expect($validations)->toHaveCount(count($result->report->evaluations));
    expect($validations->pluck('result')->contains(ValidationResult::Pass))->toBeTrue();
});

it('registra el evento de generación', function () {
    $result = generate('calidad');

    expect($result->route->usageEvents()->count())->toBe(1);
    expect($result->route->usageEvents()->sole()->event)->toBe(UsageEventType::Generada);
});

it('deja que el validador atrape lo que el compositor deje pasar', function () {
    // El compositor esquiva los traslapes leyendo las mismas reglas que el
    // validador. Si se desactiva una, el compositor deja de esquivarla —y el
    // validador la caza igual al reactivarla. Es lo que demuestra que la puerta
    // sigue siendo el validador y no la buena conducta del generador.
    CatalogRule::where('code', 'LEADERSHIP_OVERLAP')->update(['is_active' => false]);

    $request = CompositionFactory::enfocada('soft');
    $draft = app(ComposerRegistry::class)->for($request->documentType)->compose($request);

    CatalogRule::where('code', 'LEADERSHIP_OVERLAP')->update(['is_active' => true]);

    expect(validateDraft($draft))->toFailRule('LEADERSHIP_OVERLAP');
});

it('marca como rechazada la ruta que el validador no deja pasar', function () {
    // Una regla imposible de satisfacer: el documento no puede declarar cero cursos.
    CatalogRule::where('code', 'MODULE_DOUBLE_COUNT')->update([
        'type' => 'required_fields',
        'parameters' => ['source' => 'membership', 'fields' => ['full_name']],
    ]);

    $result = generate('lean');

    expect($result->approved())->toBeFalse();
    expect($result->route->status)->toBe(RouteStatus::Rejected);
    expect($result->route->access_token)->toBeNull();
    expect($result->reasons())->not->toBeEmpty();
    expect($result->route->usageEvents()->sole()->event)->toBe(UsageEventType::Rechazada);
    expect($result->route->usageEvents()->sole()->meta)->toHaveKey('blocking');
});

it('liga el documento anual al gancho que lo originó', function () {
    $enfocada = generate('lean');

    // El mismo lead vuelve, ahora como miembro.
    $anual = app(RouteGenerator::class)->generate(
        RouteInput::free(CompositionFactory::anual('lean', lead: $enfocada->route->lead)),
    );

    expect($anual->route->parent_route_id)->toBe($enfocada->route->id);
    expect($anual->route->membership_id)->not->toBeNull();
    expect($anual->route->lead_id)->toBe($enfocada->route->lead_id);
});

it('no le busca padre al gancho', function () {
    expect(generate('lean')->route->parent_route_id)->toBeNull();
});

it('no liga el anual de un lead al gancho de otro', function () {
    generate('lean'); // el gancho de un lead distinto

    $anual = app(RouteGenerator::class)->generate(RouteInput::free(CompositionFactory::anual('lean')));

    expect($anual->route->parent_route_id)->toBeNull();
});

it('genera los dos documentos del mismo lead sin pisarse', function () {
    $enfocada = generate('coretools');

    $anual = app(RouteGenerator::class)->generate(
        RouteInput::free(CompositionFactory::anual('coretools', lead: $enfocada->route->lead)),
    );

    expect(LearningRoute::where('lead_id', $enfocada->route->lead_id)->count())->toBe(2);
    expect([$enfocada->route->status, $anual->route->status])
        ->toBe([RouteStatus::Validated, RouteStatus::Validated]);
    expect($anual->route->id)->not->toBe($enfocada->route->id);
});
