<?php

declare(strict_types=1);

use App\Enums\RouteStatus;
use App\Enums\UsageEventType;
use App\Jobs\RenderRouteDocument;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LearningRoute;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FunnelFactory;

/*
 * La ruta inicial, de la captura a la entrega.
 *
 * Tres pasos, y el de en medio no se salta: «nadie ve nada todavía, tú revisas
 * antes de que salga». La captura son cinco campos, y el que decide el contenido
 * es la ruta curada: sus cursos son los que el lead va a ver como su ruta
 * principal. La ocupación y lo que dijo siguen viajando porque son lo que el
 * asesor usa para ajustar la propuesta y lo que el documento personaliza.
 */

/** La ruta curada que el asesor elige. Una sola por prueba. */
function embudo(): int
{
    return Funnel::query()->value('id')
        ?? FunnelFactory::available(
            ['siete-herramientas-de-la-calidad', 'spc', 'amef'],
            'calidad',
        )->funnel_id;
}

function capturar(array $overrides = []): array
{
    return [
        'name' => 'Mariana Cruz',
        'phone' => '4421185502',
        'occupation' => 'Ingeniera de procesos',
        'funnel_id' => embudo(),
        'context' => null,
        ...$overrides,
    ];
}

it('propone sin escribir una sola fila', function () {
    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/propuesta', capturar())
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rutas/propuesta')
            ->has('bloques')
            ->has('totales')
            ->has('curso_ids')
            ->where('bloques.0.principal', true)
            ->where('bloques.0.especialidad', 'Ingeniería de Calidad')
            // Cuántos son depende del embudo y de cuántos recomendados haya que
            // sumarle para llegar al piso: eso se prueba en CapturaDelLeadTest.
            ->has('curso_ids')
            ->has('bloques.0.justificacion'));

    // Una propuesta que el asesor descarte no debe dejar rastro en la bandeja.
    expect(Lead::count())->toBe(0);
    expect(LearningRoute::count())->toBe(0);
});

it('abre la propuesta con los cursos de la ruta curada, en su orden', function () {
    // El embudo es un preset: lo que Eje Cursos armó es lo que el asesor ve
    // precargado, no una sugerencia que el motor vuelve a componer. Lo único que
    // el motor puede añadir va detrás, y solo cuando la campaña no deja
    // suficientes recomendados; jamás reordena ni sustituye lo curado.
    $route = FunnelFactory::available(['lean-manufacturing', 'kaizen-mejora-continua'], 'lean');
    $curados = $route->orderedCourseIds();

    $ids = $this->actingAs(FunnelFactory::author())
        ->post('/rutas/propuesta', capturar(['funnel_id' => $route->funnel_id]))
        ->viewData('page')['props']['curso_ids'];

    expect(array_slice($ids, 0, count($curados)))->toBe($curados);
});

it('nombra la especialidad entera aunque la ruta lleve algunos de sus cursos', function () {
    // «No le agregamos un curso, le agregamos especialidades»: el bloque dice
    // cuántos trae la especialidad completa, aunque la ruta lleve dos o tres.
    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/propuesta', capturar())
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('bloques.0.cursos_en_la_especialidad', fn (int $total): bool => $total >= 3)
            ->etc());
});

it('emite lo que el asesor dejó, no lo que el motor propuso', function () {
    // Lo que se poda son los recomendados, que van al final de la lista. La ruta
    // principal no: esa vuelve completa aunque la petición la recorte, y eso se
    // prueba en CapturaDelLeadTest.
    Queue::fake();
    $advisor = FunnelFactory::author();

    $propuesta = $this->actingAs($advisor)->post('/rutas/propuesta', capturar());
    $ids = $propuesta->viewData('page')['props']['curso_ids'];
    $sinElUltimo = array_slice($ids, 0, -1);

    $this->actingAs($advisor)
        ->post('/rutas', [...capturar(), 'course_ids' => $sinElUltimo])
        ->assertRedirect();

    $route = LearningRoute::sole();

    expect($route->items()->count())->toBe(count($sinElUltimo));
    expect($route->items()->pluck('course_id')->all())->not->toContain(end($ids));
});

it('da de alta al lead con su ocupación y emite el enlace', function () {
    Queue::fake();

    $this->actingAs(FunnelFactory::author())
        ->post('/rutas', capturar(['context' => 'Quiere liderar la reducción de scrap']))
        ->assertRedirect();

    $lead = Lead::sole();
    $route = LearningRoute::sole();

    expect($lead->occupation)->toBe('Ingeniera de procesos');
    expect($lead->context)->toBe('Quiere liderar la reducción de scrap');
    expect($route->status)->toBe(RouteStatus::Validated);
    expect($route->access_token)->not->toBeNull();

    // La ruta curada no reparte roles de complementaria: es una lista armada a
    // mano, y lo que queda asentado es de qué ruta salió y qué especialidad la
    // abre. Los dos huecos de complementaria son del compositor automático, que
    // hoy solo interviene en el documento anual.
    expect($route->funnel_id)->toBe(embudo());
    expect($route->anchor_specialization_id)->not->toBeNull();

    Queue::assertPushed(RenderRouteDocument::class);
});

it('guarda por qué está cada bloque, para que el asesor pueda defenderlo', function () {
    Queue::fake();

    $this->actingAs(FunnelFactory::author())->post('/rutas', capturar());

    $justificaciones = LearningRoute::sole()->items()->pluck('justification')->unique()->filter();

    expect($justificaciones)->toContain('De la ruta «EJEMPLO · ruta de prueba», armada para esta campaña.');
});

it('exige la ocupación, que es de donde salen los cursos', function () {
    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/propuesta', capturar(['occupation' => '']))
        ->assertSessionHasErrors('occupation');
});

it('precarga la captura desde los parámetros del CRM', function () {
    $funnelId = embudo();
    $slug = Funnel::whereKey($funnelId)->value('slug');

    $this->actingAs(FunnelFactory::author())
        ->get("/?nombre=Mariana&telefono=4421185502&embudo={$slug}&kommo_id=A-42")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rutas/generar')
            ->where('tipo', 'inicial')
            ->where('precargado.nombre', 'Mariana')
            ->where('precargado.external_id', 'A-42')
            ->where('precargado.funnel_id', $funnelId)
            ->has('embudos'));
});

it('solo ofrece en el selector las rutas curadas que ya tienen cursos', function () {
    $conCursos = FunnelFactory::available(['siete-herramientas-de-la-calidad'], 'calidad');
    // A medio armar: existe en el listado interno, pero ofrecerla aquí sería
    // ofrecer un documento en blanco.
    FunnelFactory::funnel(['slug' => 'a-medio-armar', 'internal_name' => 'A medio armar']);

    $this->actingAs(FunnelFactory::author())
        ->get('/')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('embudos', 1)
            ->where('embudos.0.value', $conCursos->funnel_id)
            ->where('embudos.0.cursos', 1)
            ->where('embudos.0.especialidad', 'Ingeniería de Calidad')
            ->etc());
});

it('reencuentra al lead por su teléfono en vez de duplicarlo', function () {
    Queue::fake();
    $advisor = FunnelFactory::author();

    $this->actingAs($advisor)->post('/rutas', capturar());
    $this->actingAs($advisor)->post('/rutas', capturar(['name' => 'Mariana Cruz Aguilar']));

    expect(Lead::count())->toBe(1);
    expect(Lead::sole()->name)->toBe('Mariana Cruz Aguilar');
    expect(LearningRoute::count())->toBe(2);
});

it('abre la captura de la ruta final cuando se pide ese tipo', function () {
    $this->actingAs(FunnelFactory::author())
        ->get('/?tipo=final&nombre=Ana+Luc%C3%ADa&ocupacion=Coordinadora+de+calidad')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rutas/generar')
            ->where('tipo', 'final')
            ->where('precargado.nombre', 'Ana Lucía')
            ->where('precargado.occupation', 'Coordinadora de calidad')
            ->etc());
});

it('guarda la ruta aunque no haya bandeja que la liste', function () {
    Queue::fake();
    $advisor = FunnelFactory::author();

    $this->actingAs($advisor)->post('/rutas', capturar());

    // Sin pantalla de historial, la persistencia es lo que sostiene el documento
    // servido por token, el conteo de aperturas y la auditoría del validador.
    $route = LearningRoute::sole();

    expect($route->lead->name)->toBe('Mariana Cruz');
    expect($route->status)->toBe(RouteStatus::Validated);
    expect($route->access_token)->not->toBeNull();
    expect($route->validations)->not->toBeEmpty();
});

it('sirve el gancho por su enlace y asienta cada apertura', function () {
    Queue::fake();

    $this->actingAs(FunnelFactory::author())->post('/rutas', capturar());

    $route = LearningRoute::sole();

    $this->get("/r/{$route->access_token}")
        ->assertOk()
        ->assertSee('Mariana Cruz', escape: false)
        ->assertSee('Constancias DC-3', escape: false);

    expect($route->usageEvents()->where('event', UsageEventType::AbiertaPorLead)->count())->toBe(1);
});

it('no sirve un enlace inventado', function () {
    $this->get('/r/'.str_repeat('x', 48))->assertNotFound();
});

it('no sirve por enlace una ruta rechazada aunque se adivine el token', function () {
    Queue::fake();

    $this->actingAs(FunnelFactory::author())->post('/rutas', capturar());

    $route = LearningRoute::sole();
    $token = $route->access_token;

    $route->forceFill(['status' => RouteStatus::Rejected])->save();

    $this->get("/r/{$token}")->assertNotFound();
});

it('devuelve al formulario la ruta curada que se quedó sin cursos', function () {
    Queue::fake();

    // Alguien la vació mientras el asesor tenía el formulario abierto: lee qué
    // pasó, no una excepción.
    $vacia = FunnelFactory::funnel(['slug' => 'vacia', 'internal_name' => 'Vacía']);

    $this->actingAs(FunnelFactory::author())
        ->post('/rutas/propuesta', capturar(['funnel_id' => $vacia->id]))
        ->assertRedirect()
        ->assertSessionHasErrors('funnel_id');

    expect(LearningRoute::count())->toBe(0);
    expect(Lead::count())->toBe(0);
});

it('marca la ruta como lista cuando el render corre', function () {
    $this->actingAs(FunnelFactory::author())->post('/rutas', capturar());

    $route = LearningRoute::sole()->refresh();

    expect($route->status)->toBe(RouteStatus::Rendered);
    expect($route->usageEvents()->where('event', UsageEventType::Renderizada)->count())->toBe(1);
});

it('no imprime la palabra prohibida ni un solo precio', function () {
    Queue::fake();

    $this->actingAs(FunnelFactory::author())->post('/rutas', capturar());

    $html = $this->get('/r/'.LearningRoute::sole()->access_token)->getContent();

    expect(strtolower($html))->not->toContain('lecciones');
    expect($html)->not->toContain('$');
    expect($html)->not->toContain('MXN');
});

it('no acepta cursos que no existen', function () {
    // Lo que llega aquí es la lista que el asesor dejó en la propuesta. Un id que
    // no existe no llegaría de la pantalla, pero el endpoint es público al mismo
    // título que cualquier otro: sin validar, el borrador se armaría con los
    // cursos que sí existan y el documento diría otra cosa que la que se pidió.
    $this->actingAs(FunnelFactory::author())
        ->post('/rutas', capturar(['course_ids' => [999999]]))
        ->assertSessionHasErrors('course_ids.0');

    expect(LearningRoute::count())->toBe(0);
});
