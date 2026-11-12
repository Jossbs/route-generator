<?php

declare(strict_types=1);

use App\Enums\RouteStatus;
use App\Models\DocumentVisit;
use App\Models\LearningRoute;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FunnelFactory;

beforeEach(function () {
    Queue::fake();
    $funnel = FunnelFactory::available(['siete-herramientas-de-la-calidad', 'spc', 'amef'], 'calidad');
    $this->actingAs(FunnelFactory::author())->post('/rutas', [
        'name' => 'Mariana Cruz', 'phone' => '4421185502', 'occupation' => 'Ingeniera',
        'area' => 'calidad', 'funnel_id' => $funnel->funnel_id,
    ])->assertSessionHasNoErrors();
    $this->readingRoute = LearningRoute::sole();
    $this->readingUrl = $this->get('/r/'.$this->readingRoute->access_token)->assertOk()->viewData('readingEndpoint');
    $this->readingPayload = [
        'progress' => 60, 'active_ms' => 6000,
        'sections' => ['portada' => 1000, 'ruta' => 5000],
        'last_section' => 'ruta', 'furthest_section' => 'ruta', 'clicks' => [],
    ];
});

it('carga el seguimiento solo en el enlace y no cuenta la vista previa como visita activa', function () {
    $this->get('/r/'.$this->readingRoute->access_token)->assertSee('document-reading.js');
    $this->get('/rutas/'.$this->readingRoute->id.'/documento')->assertOk()->assertDontSee('document-reading.js');
    expect(DocumentVisit::count())->toBe(0);
});

it('acumula una visita sin duplicar reintentos ni retroceder por envíos fuera de orden', function () {
    $this->postJson($this->readingUrl, $this->readingPayload)->assertNoContent();
    $this->postJson($this->readingUrl, $this->readingPayload)->assertNoContent();
    $this->postJson($this->readingUrl, [
        'progress' => 100, 'active_ms' => 12000,
        'sections' => ['portada' => 1000, 'ruta' => 5000, 'siguiente-paso' => 6000],
        'last_section' => 'siguiente-paso', 'furthest_section' => 'siguiente-paso', 'clicks' => ['membresia'],
    ])->assertNoContent();
    $this->postJson($this->readingUrl, $this->readingPayload)->assertNoContent();

    $visit = DocumentVisit::sole();
    expect($visit->progress)->toBe(100)->and($visit->active_ms)->toBe(12000)
        ->and($visit->sections['ruta'])->toBe(5000)
        ->and($visit->last_section)->toBe('siguiente-paso')
        ->and($visit->furthest_section)->toBe('siguiente-paso')
        ->and($visit->clicks)->toBe(['membresia']);
});

it('separa visitas y resume secciones tiempo y seguimiento en la ficha', function () {
    $this->postJson($this->readingUrl, $this->readingPayload)->assertNoContent();
    $another = $this->get('/r/'.$this->readingRoute->access_token)->viewData('readingEndpoint');
    $this->postJson($another, $this->readingPayload)->assertNoContent();
    $this->get('/rutas/'.$this->readingRoute->id)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('lectura.visitas', 2)->where('lectura.avance', 60)->where('lectura.segundos', 12)
        ->where('lectura.seccion_mas_lejana', 'Tu ruta y cursos recomendados')
        ->where('lectura.secciones.1.segundos', 10)
        ->where('lectura.sugerencia', 'Pregunta qué curso le interesa más y si la ruta responde a su objetivo.')
    );
});

it('rechaza firmas alteradas caducadas o reutilizadas con otro token', function () {
    $this->postJson('/r/'.$this->readingRoute->access_token.'/lectura/'.Str::uuid(), $this->readingPayload)->assertForbidden();
    $this->postJson(str_replace('revision=0', 'revision=1', $this->readingUrl), $this->readingPayload)->assertForbidden();
    $this->postJson(str_replace($this->readingRoute->access_token, str_repeat('x', 48), $this->readingUrl), $this->readingPayload)->assertForbidden();
    $expired = URL::temporarySignedRoute('routes.reading', now()->subMinute(), [
        'token' => $this->readingRoute->access_token, 'visit' => (string) Str::uuid(), 'revision' => 0,
    ]);
    $this->postJson($expired, $this->readingPayload)->assertForbidden();
    expect(DocumentVisit::count())->toBe(0);
});

it('rechaza secciones inventadas y tiempos incompatibles', function () {
    $this->postJson($this->readingUrl, [...$this->readingPayload, 'sections' => ['inventada' => 1000]])->assertUnprocessable();
    $this->postJson($this->readingUrl, [...$this->readingPayload, 'active_ms' => 1000])->assertUnprocessable();
    $this->postJson($this->readingUrl, [...$this->readingPayload, 'last_section' => 'confianza'])->assertUnprocessable();
    $this->postJson($this->readingUrl, [...$this->readingPayload, 'clicks' => ['otro']])->assertUnprocessable();
    expect(DocumentVisit::count())->toBe(0);
});

it('deja de recibir actividad para rutas rechazadas o borradas', function () {
    $this->readingRoute->update(['status' => RouteStatus::Rejected]);
    $this->postJson($this->readingUrl, $this->readingPayload)->assertNotFound();
    $this->readingRoute->delete();
    $this->postJson($this->readingUrl, $this->readingPayload)->assertNotFound();
});

it('conserva el historial pero separa las revisiones y rechaza sesiones del documento anterior', function () {
    $this->postJson($this->readingUrl, $this->readingPayload)->assertNoContent();
    $this->readingRoute->update(['revisions' => 1]);
    $this->postJson($this->readingUrl, $this->readingPayload)->assertStatus(409);
    $this->get('/rutas/'.$this->readingRoute->id)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('lectura.visitas', 0)->where('lectura.avance', 0));
    expect(DocumentVisit::count())->toBe(1);
});

it('conserva el clic inmediato de contacto sin inventar tiempo de lectura', function () {
    $payload = [
        'progress' => 0, 'active_ms' => 300, 'sections' => [],
        'last_section' => null, 'furthest_section' => null, 'clicks' => ['conversacion'],
    ];
    $this->postJson($this->readingUrl, [...$payload, 'clicks' => []])->assertUnprocessable();
    $this->postJson($this->readingUrl, $payload)->assertNoContent();
    expect(DocumentVisit::sole()->clicks)->toBe(['conversacion'])
        ->and(DocumentVisit::sole()->sections)->toBeEmpty();
});
