<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\DocumentType;
use App\Models\FunnelRoute;
use App\Models\Lead;
use App\Models\LearningRoute;
use App\Models\Specialization;
use App\Services\RouteGeneration\Data\RouteInput;
use App\Services\RouteGeneration\RouteGenerator;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\FunnelFactory;

/*
 * El congelamiento.
 *
 * El documento se renderiza desde el payload, nunca desde el catálogo. Es lo que
 * hace que un gancho recibido en octubre con «vigencia al 31 de octubre» siga
 * diciendo eso en enero, y que editar un embudo mañana no mueva los documentos de
 * ayer.
 */

function issueFrom(FunnelRoute $route, ?array $courseIds = null, array $acknowledged = [])
{
    $lead = Lead::create([
        'name' => 'Rosa Elena Guerrero',
        'phone' => '8991234567',
        'source' => 'formulario',
        'advisor_id' => FunnelFactory::author()->id,
    ]);

    return app(RouteGenerator::class)->generate(RouteInput::fromFunnel(
        lead: $lead,
        documentType: DocumentType::where('slug', DocumentTypeSeeder::ENFOCADA)->sole(),
        funnelRoute: $route,
        courseIds: $courseIds,
        acknowledgedWarnings: $acknowledged,
    ));
}

it('emite la ruta del embudo en la misma tabla que la ruta libre', function () {
    // Un solo motor, una sola tabla, un solo snapshot. `funnel_id` nulo significa
    // ruta libre; las dos formas conviven.
    $route = FunnelFactory::route(['siete-herramientas-de-la-calidad', 'spc'], 'calidad');

    $result = issueFrom($route);

    expect($result->approved())->toBeTrue();
    expect($result->route)->toBeInstanceOf(LearningRoute::class);
    expect($result->route->funnel_id)->toBe($route->funnel_id);
    expect($result->route->funnel_route_id)->toBe($route->id);
    expect($result->route->cameFromFunnel())->toBeTrue();
});

it('congela el embudo dentro del payload', function () {
    $route = FunnelFactory::route(['siete-herramientas-de-la-calidad', 'spc'], 'calidad');

    $snapshot = issueFrom($route)->route->snapshot;

    // Ángulo y vigencia son columnas dormidas y viajan nulas mientras nadie las
    // capture. El nombre viaja: es el título de la portada del gancho.
    expect($snapshot['embudo'])->toMatchArray([
        'slug' => 'ruta-de-prueba',
        'nombre_publico' => 'EJEMPLO · ruta de prueba',
        'angulo' => null,
        'vigencia_inicio' => null,
        'vigencia_fin' => null,
    ]);
});

it('titula la portada con el nombre del embudo, que es el único que tiene', function () {
    // Decidido el 2026-09-24: el lead llegó por un anuncio con ese nombre. Desde
    // el 2026-09-14 el embudo tiene un solo nombre, así que ese es el que viaja;
    // uno público, si algún día se captura, manda sobre él.
    $route = FunnelFactory::route(['spc'], 'calidad');

    $snapshot = issueFrom($route)->route->snapshot;

    expect($snapshot['embudo']['nombre_publico'])->toBe('EJEMPLO · ruta de prueba');

    $route->funnel->update(['public_name' => 'Control estadístico para planta']);

    expect(issueFrom($route->refresh())->route->snapshot['embudo']['nombre_publico'])
        ->toBe('Control estadístico para planta');
});

it('deja el payload en null cuando la ruta se generó sin embudo', function () {
    $lead = Lead::create(['name' => 'Sin embudo', 'phone' => '8990000000', 'source' => 'formulario']);

    $result = app(RouteGenerator::class)->generate(new RouteInput(
        lead: $lead,
        documentType: DocumentType::where('slug', DocumentTypeSeeder::ENFOCADA)->sole(),
        anchorSpecialization: Specialization::where('slug', 'calidad')->sole(),
        courseIds: Course::whereIn('slug', ['siete-herramientas-de-la-calidad', 'spc'])->pluck('id')->all(),
    ));

    expect($result->route->snapshot['embudo'])->toBeNull();
    expect($result->route->funnel_id)->toBeNull();
    expect($result->route->cameFromFunnel())->toBeFalse();
});

it('editar el embudo no altera ningún documento ya emitido', function () {
    $route = FunnelFactory::route(['siete-herramientas-de-la-calidad', 'spc'], 'calidad');

    $issued = issueFrom($route)->route;
    $sealBefore = $issued->snapshot_hash;

    // Eje Cursos rota la campaña: renombra la ruta y le cambia los cursos.
    $route->funnel->update(['internal_name' => 'Otra cosa', 'public_name' => 'Otra cosa']);
    $route->items()->delete();
    FunnelFactory::attach($route->refresh(), ['excel-basico']);

    $issued->refresh();

    expect($issued->snapshotIsIntact())->toBeTrue();
    expect($issued->snapshot_hash)->toBe($sealBefore);
    expect($issued->snapshot['embudo']['nombre_publico'])->toBe('EJEMPLO · ruta de prueba');
    expect($issued->snapshot['embudo']['slug'])->toBe('ruta-de-prueba');
    expect(collect($issued->snapshot['ruta']['cursos'])->pluck('slug')->all())
        ->toBe(['siete-herramientas-de-la-calidad', 'spc']);
});

it('renderiza el documento desde el payload con el catálogo apagado', function () {
    // Si el render necesitara la base, el documento dejaría de ser reproducible en
    // cuanto el catálogo cambiara. El payload tiene que bastarse solo.
    $route = FunnelFactory::route(['siete-herramientas-de-la-calidad', 'spc', 'amef'], 'calidad');

    $snapshot = issueFrom($route)->route->snapshot;

    DB::disconnect();

    $rendered = collect($snapshot['ruta']['cursos'])
        ->map(fn (array $c): string => "{$c['orden']}. {$c['nombre']} · {$c['especialidad']} · {$c['horas']} h")
        ->implode("\n");

    expect($rendered)->toContain('7 Herramientas de la Calidad');
    expect($rendered)->toContain('Calidad');
    expect($rendered)->not->toContain('null');
    expect($snapshot['ruta']['totales'])->toHaveKeys(['cursos', 'programas', 'horas_netas']);
});

it('sella el payload con un hash que cambia si el payload cambia', function () {
    $route = FunnelFactory::route(['spc', 'amef'], 'calidad');

    $issued = issueFrom($route)->route;

    expect($issued->snapshot_hash)->toHaveLength(64);
    // El sello aguanta el viaje por la base: `jsonb` no conserva el orden de las
    // claves, así que se calcula sobre una forma canónica.
    expect($issued->refresh()->snapshotIsIntact())->toBeTrue();

    $tampered = $issued->snapshot;
    $tampered['ruta']['totales']['horas_netas'] = 999;
    $issued->update(['snapshot' => $tampered]);

    expect($issued->refresh()->snapshotIsIntact())->toBeFalse();
});

it('respeta la selección que el asesor modificó sobre la precarga', function () {
    // El embudo precarga, no impone.
    $route = FunnelFactory::route(['siete-herramientas-de-la-calidad', 'spc'], 'calidad');

    // El orden en que el asesor los dejó es el orden del documento.
    $elegidos = ['poka-yoke', 'siete-herramientas-de-la-calidad', 'sistema-andon'];
    $porSlug = Course::whereIn('slug', $elegidos)->pluck('id', 'slug');

    $snapshot = issueFrom(
        $route,
        courseIds: array_map(fn (string $slug): int => $porSlug[$slug], $elegidos),
    )->route->snapshot;

    expect(collect($snapshot['ruta']['cursos'])->pluck('slug')->all())->toBe($elegidos);
    expect(collect($snapshot['ruta']['cursos'])->pluck('orden')->all())->toBe([1, 2, 3]);
});

it('guarda las advertencias confirmadas para poder auditarlas después', function () {
    $route = FunnelFactory::route(['kaizen-mejora-continua', 'foda'], 'kaizen');

    $snapshot = issueFrom($route, acknowledged: ['DURATION_IN_DISPUTE'])->route->snapshot;

    $aviso = collect($snapshot['avisos_validador'])->firstWhere('codigo', 'DURATION_IN_DISPUTE');

    expect($aviso)->not->toBeNull();
    expect($aviso['confirmado'])->toBeTrue();
    expect($aviso['mensaje'])->toBeString()->not->toBeEmpty();
});

it('marca como no confirmada la advertencia que nadie reconoció', function () {
    $route = FunnelFactory::route(['kaizen-mejora-continua', 'foda'], 'kaizen');

    $snapshot = issueFrom($route)->route->snapshot;

    expect(collect($snapshot['avisos_validador'])->firstWhere('codigo', 'DURATION_IN_DISPUTE')['confirmado'])
        ->toBeFalse();
});

it('no emite enlace para una ruta que el validador rechazó', function () {
    $route = FunnelFactory::route(['liderazgo-para-ingenieros', 'liderazgo-para-lideres-y-supervisores'], 'soft');

    $result = issueFrom($route);

    expect($result->approved())->toBeFalse();
    expect($result->route->access_token)->toBeNull();
    // La ruta rechazada igual se persiste: el asesor necesita ver por qué.
    expect($result->route->exists)->toBeTrue();
    expect($result->reasons())->not->toBeEmpty();
});
