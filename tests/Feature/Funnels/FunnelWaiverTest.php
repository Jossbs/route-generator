<?php

declare(strict_types=1);

use App\Enums\ValidationResult;
use App\Models\Course;
use App\Models\DocumentType;
use App\Models\Funnel;
use App\Models\FunnelRoute;
use App\Models\Lead;
use App\Services\Funnels\FunnelRouteValidator;
use App\Services\RouteGeneration\Data\RouteInput;
use App\Services\RouteGeneration\RouteGenerator;
use Database\Seeders\DocumentTypeSeeder;
use Tests\Support\FunnelFactory;

/*
 * Las dos salidas de un hallazgo en el armador, decididas el 2026-09-22: corregir
 * la lista con una sugerencia, o ignorarlo y seguir con la ruta como está.
 *
 * Ignorar se guarda en el embudo y el documento lo respeta, con dos límites que
 * son lo que se prueba aquí: las ocho restricciones de docs/ARQUITECTURA.md §4 no se ignoran
 * nunca, y la dispensa no alcanza a cursos que el embudo no curó.
 */

function waivedRoute(array $courseSlugs, array $waived, ?string $main = null): FunnelRoute
{
    return FunnelFactory::route($courseSlugs, $main, [], [
        'waived_rules' => array_map(fn (string $code): array => ['code' => $code, 'by' => null, 'at' => now()->toIso8601String()], $waived),
    ]);
}

function emitWaived(FunnelRoute $route, ?array $courseIds = null)
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
    ));
}

function waiverCourseIds(array $slugs): array
{
    $bySlug = Course::whereIn('slug', $slugs)->pluck('id', 'slug');

    return array_map(fn (string $slug): int => $bySlug[$slug], $slugs);
}

it('deja de bloquear lo que el embudo ignoró', function () {
    $route = waivedRoute(['green-belt-six-sigma', 'black-belt-six-sigma'], ['DMAIC_OVERLAP'], 'sixsigma');

    $result = app(FunnelRouteValidator::class)->validate($route);

    expect(array_column($result->bloqueantes, 'codigo'))->not->toContain('DMAIC_OVERLAP');
    expect(array_column($result->ignorados, 'codigo'))->toContain('DMAIC_OVERLAP');
    expect($result->publicable())->toBeTrue();
});

it('emite el documento y deja asentado que la regla falló y se ignoró', function () {
    $route = waivedRoute(['green-belt-six-sigma', 'black-belt-six-sigma'], ['DMAIC_OVERLAP'], 'sixsigma');

    $result = emitWaived($route);

    expect($result->approved())->toBeTrue();

    $validation = $result->route->validations()->where('rule_code', 'DMAIC_OVERLAP')->sole();

    expect($validation->result)->toBe(ValidationResult::Fail);
    expect($validation->details['waived'])->toBeTrue();
});

it('no alcanza a un curso que el embudo no curó', function () {
    // El embudo ignoró el traslape entre sus dos cinturones. Si al emitir se suma
    // DMAIC, ese conflicto ya no es el que alguien revisó.
    $route = waivedRoute(['green-belt-six-sigma', 'black-belt-six-sigma'], ['DMAIC_OVERLAP'], 'sixsigma');

    $result = emitWaived($route, waiverCourseIds(['green-belt-six-sigma', 'black-belt-six-sigma', 'dmaic']));

    expect($result->approved())->toBeFalse();
});

it('nunca ignora una de las ocho restricciones críticas', function () {
    $route = waivedRoute(
        ['liderazgo-para-ingenieros', 'liderazgo-para-lideres-y-supervisores'],
        ['LEADERSHIP_OVERLAP'],
        'soft',
    );

    $result = app(FunnelRouteValidator::class)->validate($route);

    expect(array_column($result->bloqueantes, 'codigo'))->toContain('LEADERSHIP_OVERLAP');
    expect(collect($result->bloqueantes)->firstWhere('codigo', 'LEADERSHIP_OVERLAP')['ignorable'])->toBeFalse();
    expect($result->publicable())->toBeFalse();
});

it('rechaza desde el formulario ignorar una restricción crítica', function () {
    $this->actingAs(FunnelFactory::author())
        ->post('/embudos', [
            'name' => 'Liderazgo',
            'items' => array_map(fn (int $id): array => ['course_id' => $id], waiverCourseIds(['liderazgo-para-ingenieros'])),
            'waived_rules' => ['LEADERSHIP_OVERLAP'],
        ])
        ->assertSessionHasErrors('waived_rules.0');

    expect(Funnel::count())->toBe(0);
});

it('guarda quién ignoró qué, y no lo vuelve a firmar al guardar de nuevo', function () {
    $author = FunnelFactory::author();
    $items = array_map(fn (int $id): array => ['course_id' => $id], waiverCourseIds(['green-belt-six-sigma', 'dmaic']));

    $this->actingAs($author)
        ->post('/embudos', ['name' => 'Six Sigma', 'items' => $items, 'waived_rules' => ['DMAIC_OVERLAP']])
        ->assertSessionHasNoErrors();

    $funnel = Funnel::sole();
    $firma = $funnel->waived_rules[0];

    expect($firma['code'])->toBe('DMAIC_OVERLAP');
    expect($firma['by'])->toBe($author->id);

    $this->travel(1)->days();

    $this->actingAs($author)
        ->put("/embudos/{$funnel->id}", ['name' => 'Six Sigma', 'items' => $items, 'waived_rules' => ['DMAIC_OVERLAP']])
        ->assertSessionHasNoErrors();

    expect($funnel->refresh()->waived_rules)->toBe([$firma]);

    $this->actingAs($author)
        ->put("/embudos/{$funnel->id}", ['name' => 'Six Sigma', 'items' => $items, 'waived_rules' => []])
        ->assertSessionHasNoErrors();

    expect($funnel->refresh()->waivedRuleCodes())->toBe([]);
});

it('sugiere quedarse con uno de los cursos que se excluyen', function () {
    $route = FunnelFactory::route(['green-belt-six-sigma', 'dmaic', 'white-belt-six-sigma'], 'sixsigma');

    $finding = collect(app(FunnelRouteValidator::class)->validate($route)->bloqueantes)
        ->firstWhere('codigo', 'DMAIC_OVERLAP');

    expect($finding['ignorable'])->toBeTrue();
    expect(array_column($finding['sugerencias'], 'curso_ids'))->toEqualCanonicalizing([
        waiverCourseIds(['green-belt-six-sigma', 'white-belt-six-sigma']),
        waiverCourseIds(['dmaic', 'white-belt-six-sigma']),
    ]);
});

it('sugiere quitar lo que un paquete ya contiene', function () {
    $route = FunnelFactory::route(['excel-completo', 'excel-avanzado', 'power-bi'], 'excel');

    $finding = collect(app(FunnelRouteValidator::class)->validate($route)->bloqueantes)
        ->firstWhere('codigo', 'PACKAGE_COMPONENT_OVERLAP');

    expect(array_column($finding['sugerencias'], 'curso_ids'))->toContain(waiverCourseIds(['excel-completo', 'power-bi']));
});

it('sugiere acomodar los niveles en orden', function () {
    $route = FunnelFactory::route(['excel-avanzado', 'power-bi', 'excel-basico'], 'excel');

    $finding = collect(app(FunnelRouteValidator::class)->validate($route)->bloqueantes)
        ->firstWhere('codigo', 'LEVEL_SEQUENCE');

    // Crítica: no se ignora, pero sí se corrige con un clic.
    expect($finding['ignorable'])->toBeFalse();
    expect($finding['sugerencias'][0]['curso_ids'])->toBe(waiverCourseIds(['excel-basico', 'excel-avanzado', 'power-bi']));
});

it('valida y sugiere mientras se arma un embudo que todavía no existe', function () {
    $this->actingAs(FunnelFactory::author())
        ->post('/embudos/validacion', [
            'items' => array_map(fn (int $id): array => ['course_id' => $id], waiverCourseIds(['green-belt-six-sigma', 'dmaic'])),
            'copy_text' => ['Six Sigma'],
            'waived_rules' => [],
        ])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('embudos/nuevo')
            ->where('validacionEnVivo.bloqueantes.0.codigo', 'DMAIC_OVERLAP')
            ->where('validacionEnVivo.bloqueantes.0.ignorable', true)
            ->has('validacionEnVivo.bloqueantes.0.sugerencias', 2));

    expect(Funnel::count())->toBe(0);
});
