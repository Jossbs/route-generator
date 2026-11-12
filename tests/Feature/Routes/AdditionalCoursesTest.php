<?php

declare(strict_types=1);

use App\Enums\RouteStatus;
use App\Models\Course;
use App\Models\LearningRoute;
use App\Services\Documents\DocumentView;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FunnelFactory;

function additionalCapture(array $extra = []): array
{
    $funnel = FunnelFactory::available(['siete-herramientas-de-la-calidad'], 'calidad');

    return [
        'name' => 'Mariana Cruz', 'occupation' => 'Ingeniera', 'area' => 'calidad',
        'funnel_id' => $funnel->funnel_id,
        'course_ids' => [...$funnel->orderedCourseIds(), ...array_map(courseId(...), $extra)],
    ];
}

it('permite más de tres adicionales y cursos no curados de la misma especialidad principal', function () {
    Queue::fake();
    $capture = additionalCapture(['amef', 'spc', 'ppap', 'plan-de-control']);
    $this->actingAs(FunnelFactory::author())->postJson('/rutas/validacion', $capture)
        ->assertOk()->assertJsonPath('bloqueos', [])->assertJsonPath('totales.cursos', 5);
    $this->postJson('/rutas', $capture)->assertOk();
    expect(LearningRoute::sole()->itineraryCourseIds())->toBe($capture['course_ids']);
});

it('deja al asesor decidir traslapes y niveles sin duplicar las horas del paquete', function () {
    Queue::fake();
    $capture = additionalCapture(['excel-avanzado', 'ppap', 'excel-basico', 'excel-completo', 'liderazgo-para-ingenieros', 'liderazgo-para-lideres-y-supervisores']);
    $result = $this->actingAs(FunnelFactory::author())->postJson('/rutas/validacion', $capture)
        ->assertOk()->assertJsonPath('bloqueos', [])->json();
    expect(array_column($result['advertencias'], 'codigo'))->toContain('LEVEL_SEQUENCE', 'LEADERSHIP_OVERLAP', 'PACKAGE_COMPONENT_OVERLAP');
    $this->postJson('/rutas', $capture)->assertOk();
    $route = LearningRoute::sole();
    $warning = $route->validations->firstWhere('rule_code', 'LEADERSHIP_OVERLAP');
    expect($route->status)->toBe(RouteStatus::Validated)
        ->and($warning->details['severity'])->toBe('warning')
        ->and($warning->details['advisor_recommendation'])->toBeTrue()
        ->and($route->snapshot['ruta']['totales']['horas_netas'])->toBe($result['totales']['horas_netas']);
});

it('puede incluir un adicional sin horas publicadas con un aviso claro', function () {
    Queue::fake();
    $capture = additionalCapture();
    $unknown = Course::query()->where('slug', 'liderazgo-efectivo')->firstOrFail();
    $unknown->update(['duration_hours' => null, 'allowed_layers' => ['inventario']]);
    $capture['course_ids'][] = $unknown->id;
    $result = $this->actingAs(FunnelFactory::author())->postJson('/rutas/validacion', $capture)
        ->assertOk()->assertJsonPath('bloqueos', [])->json();
    expect(array_column($result['advertencias'], 'codigo'))->toContain('COURSE_LAYER_RESTRICTION');
    $this->postJson('/rutas', $capture)->assertOk();
    expect(LearningRoute::sole()->itineraryCourseIds())->toContain($unknown->id);
});

it('mantiene el orden intercalado en la propuesta el snapshot y el documento', function () {
    Queue::fake();
    $capture = additionalCapture(['excel-avanzado', 'ppap', 'excel-basico']);
    $this->actingAs(FunnelFactory::author());
    $proposal = $this->post('/rutas/propuesta', $capture)->viewData('page')['props'];
    expect(array_merge(...array_map(fn (array $block): array => array_column($block['cursos'], 'id'), $proposal['bloques'])))
        ->toBe($capture['course_ids']);
    $this->postJson('/rutas', $capture)->assertOk();
    $route = LearningRoute::sole();
    expect($route->itineraryCourseIds())->toBe($capture['course_ids']);
    $html = $this->get('/rutas/'.$route->id.'/documento')->assertOk()->getContent();
    preg_match_all('#<div class="cu-nom">([^<]+)#', $html, $names);
    expect(array_map(html_entity_decode(...), $names[1]))->toBe(Course::query()->whereIn('id', $capture['course_ids'])->get()->sortBy(fn (Course $c): int => array_search($c->id, $capture['course_ids'], true))->pluck('name')->all());
});

it('conserva una sustitución y el nuevo orden al corregir y reabrir la propuesta', function () {
    Queue::fake();
    $capture = additionalCapture(['amef', 'spc', 'ppap']);
    $this->actingAs(FunnelFactory::author())->postJson('/rutas', $capture)->assertOk();
    $route = LearningRoute::sole();
    $token = $route->access_token;
    $capture['course_ids'] = [$capture['course_ids'][0], courseId('excel-avanzado'), courseId('ppap'), courseId('excel-basico'), courseId('plan-de-control')];
    $this->putJson('/rutas/'.$route->id, $capture)->assertOk();
    $proposal = $this->post('/rutas/'.$route->id.'/propuesta', $capture)->assertOk()->viewData('page')['props'];
    expect($proposal['curso_ids'])->toBe($capture['course_ids'])
        ->and($route->fresh()->itineraryCourseIds())->toBe($capture['course_ids'])
        ->and($route->fresh()->access_token)->toBe($token);
});

it('no convierte en avisos los conflictos propios de la campaña', function () {
    $funnel = FunnelFactory::available(['liderazgo-para-ingenieros', 'liderazgo-para-lideres-y-supervisores'], 'soft');
    $capture = ['name' => 'Mariana', 'occupation' => 'Ingeniera', 'area' => 'calidad', 'funnel_id' => $funnel->funnel_id,
        'course_ids' => [...$funnel->orderedCourseIds(), courseId('liderazgo-efectivo')]];
    $result = $this->actingAs(FunnelFactory::author())->postJson('/rutas/validacion', $capture)->assertOk()->json();
    expect(array_column($result['bloqueos'], 'codigo'))->toContain('LEADERSHIP_OVERLAP');
    $this->postJson('/rutas', $capture)->assertUnprocessable();
    expect(LearningRoute::count())->toBe(0);
});

it('distribuye muchos adicionales en continuaciones sin perder cursos ni su orden', function () {
    Queue::fake();
    $capture = additionalCapture();
    $extra = Course::query()->whereNotIn('id', $capture['course_ids'])->whereNotNull('duration_hours')->limit(14)->pluck('id')->all();
    $capture['course_ids'] = [...$capture['course_ids'], ...$extra];
    $this->actingAs(FunnelFactory::author())->postJson('/rutas', $capture)->assertOk();
    $route = LearningRoute::sole();
    $pages = app(DocumentView::class)->for($route)->getData()['hojasRuta'];
    expect(count($pages))->toBeGreaterThan(4);
    $ordered = [];
    foreach ($pages as $page) {
        $extrasOnPage = 0;
        foreach ($page as $block) {
            $ordered = [...$ordered, ...array_column($block['cursos'], 'slug')];
            if ($block['rol'] === 'complementaria') {
                $extrasOnPage += count($block['cursos']);
            }
        }
        expect($extrasOnPage)->toBeLessThanOrEqual(3);
    }
    $slugs = Course::query()->whereIn('id', $capture['course_ids'])->pluck('slug', 'id');
    expect($ordered)->toBe(array_map(fn (int $id): string => $slugs[$id], $capture['course_ids']));
});
