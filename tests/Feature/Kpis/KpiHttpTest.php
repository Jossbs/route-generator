<?php

declare(strict_types=1);

use App\Enums\Kpis\MeasureAggregation;
use App\Models\KpiTarget;
use App\Models\SalesEvent;
use App\Models\User;
use App\Services\Kpis\Period;
use App\Services\Kpis\Reports\KpiReportPdf;
use App\Services\Kpis\Scope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\KpiFactory;

/*
 * Las pantallas: el tablero lo ve todo ventas; capturar, fijar metas, cerrar y
 * configurar es del administrador.
 */

beforeEach(function () {
    $this->venta = KpiFactory::saleType();
    $this->kpi = KpiFactory::kpi();
    $this->conteo = KpiFactory::aggregate($this->kpi, $this->venta, MeasureAggregation::Conteo);
    $this->admin = User::factory()->create(['is_kpi_admin' => true]);
    $this->asesor = User::factory()->create(['is_kpi_admin' => false]);
    $this->travelTo(CarbonImmutable::parse('2026-09-20 18:00:00', 'UTC'));
});

it('muestra el tablero a cualquiera, con periodo y alcance por URL', function () {
    KpiFactory::sale($this->venta, $this->asesor, '2026-09-10');

    $this->actingAs($this->asesor)
        ->get('/kpis?periodo=semana&fecha=2026-09-10')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kpis/tablero')
            ->where('periodo.start', '2026-09-07')
            ->where('periodo.type', 'semana')
            ->where('puedeAdministrar', false)
            ->where('kpis.0.measures.0.progress.value', 1)
            ->has('kpis.0.measures.0.series', 7));
});

it('la captura, las metas, los cierres y la configuración son del administrador', function (string $url) {
    $this->actingAs($this->asesor)->get($url)->assertForbidden();
    $this->actingAs($this->admin)->get($url)->assertOk();
})->with(['/kpis/eventos', '/kpis/metas', '/kpis/cierres', '/kpis/configuracion']);

it('el administrador captura un evento', function () {
    $this->actingAs($this->admin)
        ->post('/kpis/eventos', [
            'sales_event_type_id' => $this->venta->id,
            'user_id' => $this->asesor->id,
            'occurred_on' => '2026-09-19',
            'values' => ['plan' => 'duo', 'importe' => 5999],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(SalesEvent::sole()->values)->toEqual(['plan' => 'duo', 'importe' => 5999])
        ->and(SalesEvent::sole()->created_by)->toBe($this->admin->id);
});

it('un asesor no puede capturar', function () {
    $this->actingAs($this->asesor)->post('/kpis/eventos', [])->assertForbidden();
});

it('guarda las metas del trimestre desde la tabla', function () {
    $this->actingAs($this->admin)
        ->post('/kpis/metas', [
            'kpi_measure_id' => $this->conteo->id,
            'reason' => 'Plan del T3',
            'cells' => [
                ['scope' => 'general', 'id' => null, 'period_type' => 'mes', 'period_start' => '2026-07-01', 'value' => 10],
                ['scope' => 'general', 'id' => null, 'period_type' => 'mes', 'period_start' => '2026-08-01', 'value' => 20],
                ['scope' => 'persona', 'id' => $this->asesor->id, 'period_type' => 'mes', 'period_start' => '2026-09-01', 'value' => 30],
            ],
        ])
        ->assertSessionHasNoErrors();

    expect(KpiTarget::query()->count())->toBe(3);

    $this->actingAs($this->admin)
        ->get('/kpis/metas?fecha=2026-08-01&medida='.$this->conteo->id)
        ->assertInertia(fn (Assert $page) => $page
            ->where('filas.0.months', [10, 20, null])
            ->where('filas.0.months_sum', 30)
            ->has('historial', 3));
});

it('descarga el resumen en CSV y el reporte en PDF', function () {
    config(['documents.gotenberg.url' => 'http://gotenberg.test']);
    Http::fake(['gotenberg.test/*' => Http::response('%PDF-1.7 falso')]);
    KpiFactory::sale($this->venta, $this->asesor, '2026-09-10');

    $csv = $this->actingAs($this->asesor)->get('/kpis/reporte.csv?periodo=mes&fecha=2026-09-10');
    $csv->assertOk();

    expect($csv->streamedContent())->toContain('KPI,Medida')->toContain('Todo ventas');

    $this->actingAs($this->asesor)
        ->get('/kpis/reporte.pdf?periodo=mes&fecha=2026-09-10')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/forms/chromium/convert/html'));
});

it('el reporte PDF lleva los números del tablero', function () {
    KpiFactory::sale($this->venta, $this->asesor, '2026-09-10');

    $html = app(KpiReportPdf::class)->view(
        Period::fromInput('mes', '2026-09-10'),
        Scope::general(),
        'Todo ventas',
    )->render();

    expect($html)->toContain('KPIs de ventas')->toContain('Ventas')->toContain($this->asesor->name);
});

it('rechaza metas con una persona o equipo inexistente', function (string $scope, ?int $id) {
    $this->actingAs($this->admin)->post('/kpis/metas', [
        'kpi_measure_id' => $this->conteo->id,
        'cells' => [[
            'scope' => $scope, 'id' => $id, 'period_type' => 'mes',
            'period_start' => '2026-09-01', 'value' => 10,
        ]],
    ])->assertSessionHasErrors('cells.0.id');

    expect(KpiTarget::count())->toBe(0);
})->with([['persona', null], ['equipo', null], ['persona', 99999], ['equipo', 99999], ['general', 99999]]);

it('exporta texto de los eventos sin ejecutarlo como fórmula en Excel', function () {
    $this->asesor->update(['name' => '=HYPERLINK("https://example.com")']);
    KpiFactory::sale($this->venta, $this->asesor, '2026-09-10')->update(['notes' => '+SUM(1,2)']);

    $response = $this->actingAs($this->admin)->get('/kpis/eventos.csv?fecha=2026-09-10');
    $response->assertOk();
    $lines = explode("\n", substr($response->streamedContent(), 3));
    $row = str_getcsv($lines[1], escape: '');

    expect($row[2])->toBe('\'=HYPERLINK("https://example.com")')
        ->and($row)->toContain("'+SUM(1,2)");
});
