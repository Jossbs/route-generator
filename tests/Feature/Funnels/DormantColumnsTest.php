<?php

declare(strict_types=1);

use App\Models\Archetype;
use App\Models\DocumentType;
use App\Models\Funnel;
use App\Models\FunnelRoute;
use App\Models\FunnelRouteCourse;
use App\Models\Lead;
use App\Services\RouteGeneration\Data\RouteInput;
use App\Services\RouteGeneration\RouteGenerator;
use Illuminate\Support\Facades\Schema;
use Tests\Support\FunnelFactory;

/*
 * El principio rector del módulo: diseñar el modelo completo, construir solo lo
 * mínimo.
 *
 * Estas columnas existen para no migrar después. La prueba vigila las dos mitades
 * de esa promesa: que la columna esté —o el día que haga falta habrá una migración
 * en medio de una campaña— y que nada la lea ni la escriba, porque una columna
 * dormida con lógica encima ya no está dormida.
 */

it('crea las columnas dormidas del embudo', function (string $table, string $column) {
    expect(Schema::hasColumn($table, $column))->toBeTrue();
})->with([
    // Bloqueadas por §11.2: la administración del descuento sigue sin decidirse.
    ['funnels', 'list_price_cents'],
    ['funnels', 'promo_price_cents'],
    ['funnels', 'discount_label'],
    ['funnels', 'price_anchor_course_id'],
    // Trazabilidad publicitaria, post-v1.
    ['funnels', 'utm_campaign'],
    ['funnels', 'meta_campaign_ref'],
    // La mecánica «elige N de M» del sitio comercial histórico, que va a regresar.
    ['funnel_routes', 'choose_n'],
    ['funnel_route_courses', 'kind'],
    // La válvula de escape para que una promoción lleve su propia frase sin que
    // nadie fabrique contenido en el catálogo maestro.
    ['funnel_route_courses', 'outcome_phrase_override'],
    // Detección automática de arquetipo, fuera del alcance de la v1.
    ['archetypes', 'match_rules'],
]);

it('las deja vacías: nada las escribe', function () {
    FunnelFactory::route(['siete-herramientas-de-la-calidad', 'spc'], 'calidad');

    $funnel = Funnel::sole();

    expect($funnel->list_price_cents)->toBeNull();
    expect($funnel->promo_price_cents)->toBeNull();
    expect($funnel->discount_label)->toBeNull();
    expect($funnel->price_anchor_course_id)->toBeNull();
    expect($funnel->utm_campaign)->toBeNull();
    expect($funnel->meta_campaign_ref)->toBeNull();

    expect(FunnelRoute::sole()->choose_n)->toBeNull();
    expect(FunnelRouteCourse::pluck('outcome_phrase_override')->filter()->all())->toBe([]);
    expect(Archetype::pluck('match_rules')->filter()->all())->toBe([]);
});

it('no imprime precios en el documento mientras la decisión siga abierta', function () {
    // El documento cuantifica horas, programas y constancias DC-3. El precio lo
    // dice el asesor en la conversación, donde puede manejar objeciones.
    $route = FunnelFactory::route(['spc', 'amef'], 'calidad');

    $lead = Lead::create(['name' => 'Prueba', 'phone' => '8990000000', 'source' => 'formulario']);

    $result = app(RouteGenerator::class)->generate(
        RouteInput::fromFunnel(
            lead: $lead,
            documentType: DocumentType::where('slug', 'ruta-enfocada')->sole(),
            funnelRoute: $route,
        ),
    );

    expect($result->route->snapshot['precios'])->toBeNull();
});

it('no fabrica frases de resultado desde el override dormido', function () {
    // Las 64 frases del catálogo maestro siguen sin aprobarse. La columna existe y
    // se queda vacía: sigue prohibido inventarlas.
    $route = FunnelFactory::route(['spc'], 'calidad');

    $route->items()->first()->update(['outcome_phrase_override' => 'Texto que nadie aprobó']);

    $lead = Lead::create(['name' => 'Prueba', 'phone' => '8990000001', 'source' => 'formulario']);

    $result = app(RouteGenerator::class)->generate(
        RouteInput::fromFunnel(
            lead: $lead,
            documentType: DocumentType::where('slug', 'ruta-enfocada')->sole(),
            funnelRoute: $route->refresh(),
        ),
    );

    expect(json_encode($result->route->snapshot))->not->toContain('Texto que nadie aprobó');
});
