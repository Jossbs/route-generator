<?php

declare(strict_types=1);

use App\Enums\RouteStatus;
use App\Models\CatalogRule;
use App\Models\Course;
use App\Models\Lead;
use App\Models\LearningRoute;
use App\Services\RouteGeneration\Data\LeadCapture;
use App\Services\RouteGeneration\RouteIssuer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FunnelFactory;

function editableInitialCapture(): array
{
    $funnel = FunnelFactory::available(['siete-herramientas-de-la-calidad', 'liderazgo-para-ingenieros'], 'calidad');

    return [
        'name' => 'Mariana Benavides',
        'occupation' => 'Ingeniera de calidad',
        'area' => 'calidad',
        'funnel_id' => $funnel->funnel_id,
        'course_ids' => [
            ...$funnel->orderedCourseIds(),
            courseId('green-belt-six-sigma'),
            courseId('yellow-belt-six-sigma'),
            courseId('liderazgo-para-lideres-y-supervisores'),
        ],
        'ajustes' => ['objetivo.ruta-enfocada' => 'Una ruta para mejorar mis procesos y fortalecer mi desarrollo profesional.'],
    ];
}

it('revisa los cursos editados sin guardar y distingue conflictos de advertencias', function () {
    $capture = editableInitialCapture();

    $this->actingAs(FunnelFactory::author())->postJson('/rutas/validacion', $capture)
        ->assertOk()
        ->assertJsonCount(1, 'bloqueos')
        ->assertJsonPath('bloqueos.0.codigo', 'LEADERSHIP_OVERLAP')
        ->assertJsonPath('advertencias.0.codigo', 'PREREQUISITE_SUGGESTED')
        ->assertJsonPath('totales.cursos', 5)
        ->assertJsonPath('totales.horas_netas', 91)
        ->assertJsonPath('bloqueos.0.mensaje', fn (string $message): bool => str_contains($message, 'Liderazgo para Ingenieros') && str_contains($message, 'Liderazgo para Líderes y Supervisores'));

    expect(Lead::count())->toBe(0)->and(LearningRoute::count())->toBe(0);
});

it('actualiza los totales y permite generar con solo una advertencia', function () {
    Queue::fake();
    $capture = editableInitialCapture();
    array_pop($capture['course_ids']);

    $this->actingAs(FunnelFactory::author())->postJson('/rutas/validacion', $capture)
        ->assertOk()
        ->assertJsonPath('bloqueos', [])
        ->assertJsonCount(1, 'advertencias')
        ->assertJsonPath('totales.cursos', 4)
        ->assertJsonPath('totales.horas_netas', 79);

    $this->postJson('/rutas', $capture)->assertOk()->assertJsonStructure(['url']);
    $route = LearningRoute::sole();
    expect($route->status)->toBe(RouteStatus::Validated)
        ->and($route->snapshot['ajustes_copy'])->toBe($capture['ajustes']);
});

it('recalcula horas netas y programas sin contar dos veces un curso completo', function () {
    $funnel = FunnelFactory::available(['excel-completo'], 'excel');
    $capture = [
        'name' => 'Prueba', 'occupation' => 'Analista', 'area' => 'administracion', 'funnel_id' => $funnel->funnel_id,
        'course_ids' => [courseId('excel-completo'), courseId('excel-basico')],
    ];

    $this->actingAs(FunnelFactory::author())->postJson('/rutas/validacion', $capture)
        ->assertOk()
        ->assertJsonPath('totales.cursos', 2)
        ->assertJsonPath('totales.programas', 1)
        ->assertJsonPath('totales.horas_netas', Course::findOrFail(courseId('excel-completo'))->duration_hours);
});

it('vuelve a validar al generar e impide guardar un borrador conflictivo', function () {
    Queue::fake();
    $capture = editableInitialCapture();

    $this->actingAs(FunnelFactory::author())->postJson('/rutas', $capture)
        ->assertUnprocessable()->assertJsonValidationErrors('course_ids');

    expect(LearningRoute::count())->toBe(0)->and(Lead::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('conserva el PDF, los datos y el enlace cuando se rechaza una corrección', function () {
    Queue::fake();
    Storage::fake('local');
    $capture = editableInitialCapture();
    $valid = $capture;
    array_pop($valid['course_ids']);
    $advisor = FunnelFactory::author();
    $this->actingAs($advisor)->postJson('/rutas', $valid)->assertOk();
    $route = LearningRoute::sole();
    $route->update(['status' => RouteStatus::Rendered, 'pdf_path' => 'documentos/original.pdf']);
    Storage::disk('local')->put($route->pdf_path, '%PDF-original');
    $original = $route->getAttributes();
    Queue::fake();

    $this->putJson('/rutas/'.$route->id, [...$capture, 'name' => 'Nombre que no debe guardarse'])
        ->assertUnprocessable()->assertJsonValidationErrors('course_ids');

    expect($route->fresh()->getAttributes())->toBe($original)
        ->and($route->lead->fresh()->name)->toBe('Mariana Benavides')
        ->and(Storage::disk('local')->get($route->pdf_path))->toBe('%PDF-original');
    Queue::assertNothingPushed();
});

it('revisa también los textos y deja corregirlos en el mismo borrador', function () {
    $capture = editableInitialCapture();
    array_pop($capture['course_ids']);
    $capture['ajustes']['objetivo.ruta-enfocada'] = 'Es equivalente a una certificación Green Belt.';

    $this->actingAs(FunnelFactory::author())->postJson('/rutas/validacion', $capture)
        ->assertUnprocessable()->assertJsonValidationErrors('ajustes.objetivo.ruta-enfocada');
    expect(LearningRoute::count())->toBe(0);

    $capture['ajustes']['objetivo.ruta-enfocada'] = 'Quiero mejorar el análisis de mis procesos.';
    $this->postJson('/rutas/validacion', $capture)->assertOk()->assertJsonPath('bloqueos', []);
});

it('explica los rechazos anteriores sin confundir advertencias con bloqueos', function () {
    $capture = editableInitialCapture();
    $advisor = FunnelFactory::author();
    $route = app(RouteIssuer::class)->issueFocused(LeadCapture::fromArray($capture), $advisor, $capture['course_ids'])->route;

    // Reproduce registros anteriores, que no conservaban mensaje ni gravedad.
    foreach ($route->validations as $validation) {
        $validation->update(['details' => Arr::except($validation->details ?? [], ['severity', 'message'])]);
    }

    $this->actingAs($advisor)->get('/rutas/'.$route->id)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rechazos', 1)->where('rechazos.0.codigo', 'LEADERSHIP_OVERLAP')
            ->where('rechazos.0.mensaje', fn (string $message): bool => str_contains($message, 'Liderazgo para Ingenieros'))
            ->has('advertencias', 1)->where('advertencias.0.codigo', 'PREREQUISITE_SUGGESTED'));

    $this->postJson('/rutas/'.$route->id.'/validacion', $capture)->assertOk();
});

it('conserva la gravedad registrada aunque cambie el catálogo después', function () {
    $capture = editableInitialCapture();
    $advisor = FunnelFactory::author();
    $route = app(RouteIssuer::class)->issueFocused(LeadCapture::fromArray($capture), $advisor, $capture['course_ids'])->route;
    CatalogRule::where('code', 'LEADERSHIP_OVERLAP')->update(['severity' => 'warning']);

    $this->actingAs($advisor)->get('/rutas/'.$route->id)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rechazos', 1)->where('rechazos.0.codigo', 'LEADERSHIP_OVERLAP'));
});
