<?php

declare(strict_types=1);

use App\Enums\Kpis\DigestKind;
use App\Enums\Kpis\MeasureAggregation;
use App\Models\KpiDigest;
use App\Models\User;
use App\Services\Kpis\Digests\DigestSender;
use App\Services\Kpis\PeriodCloser;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\Support\KpiFactory;

/*
 * El cierre de mes congela lo reportado; los resúmenes van al grupo de Telegram
 * y se quedan en el módulo aunque el envío falle (decidido el 2026-09-26).
 */

beforeEach(function () {
    $this->venta = KpiFactory::saleType();
    $this->kpi = KpiFactory::kpi();
    KpiFactory::aggregate($this->kpi, $this->venta, MeasureAggregation::Conteo, attrs: ['name' => 'Membresías']);
    $this->admin = User::factory()->create();
    $this->ana = User::factory()->create(['name' => 'Ana']);
    KpiFactory::assign($this->ana, KpiFactory::team('Norte'), '2026-01-01');
    $this->travelTo(CarbonImmutable::parse('2026-10-03 18:00:00', 'UTC'));
});

it('congela los números del mes, por equipo y por persona', function () {
    KpiFactory::sale($this->venta, $this->ana, '2026-09-10');
    KpiFactory::sale($this->venta, $this->ana, '2026-09-11');

    $cierre = app(PeriodCloser::class)->close(CarbonImmutable::parse('2026-09-01'), $this->admin);
    $medida = $cierre->snapshot['general'][0]['measures'][0];

    expect($medida['progress']['value'])->toEqual(2)
        ->and($medida['ranking'][0]['name'])->toBe('Ana')
        ->and($cierre->snapshot['teams'][0]['name'])->toBe('Norte')
        ->and($cierre->closed_by)->toBe($this->admin->id);
});

it('no cierra un mes que no ha terminado ni uno ya cerrado', function () {
    $closer = app(PeriodCloser::class);

    expect(fn () => $closer->close(CarbonImmutable::parse('2026-10-01'), $this->admin))->toThrow(ValidationException::class);

    $closer->close(CarbonImmutable::parse('2026-09-01'), $this->admin);

    expect(fn () => $closer->close(CarbonImmutable::parse('2026-09-01'), $this->admin))->toThrow(ValidationException::class);
});

it('manda el resumen del día al grupo y lo guarda como se mandó', function () {
    config(['kpis.telegram.bot_token' => 'token', 'kpis.telegram.chat_id' => '-100']);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    KpiFactory::sale($this->venta, $this->ana, '2026-10-03');

    $resumen = app(DigestSender::class)->send(DigestKind::Diario, CarbonImmutable::parse('2026-10-03'));

    expect($resumen->sent_at)->not->toBeNull()
        ->and($resumen->body)->toContain('Resumen del día')
        ->and($resumen->body)->toContain('Membresías')
        ->and($resumen->body)->toContain('🏆 Ana');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/bottoken/sendMessage')
        && $request['chat_id'] === '-100'
        && $request['parse_mode'] === 'HTML');
});

it('sin Telegram configurado el resumen se guarda con el motivo', function () {
    config(['kpis.telegram.bot_token' => null]);
    Http::fake();

    $resumen = app(DigestSender::class)->send(DigestKind::Semanal, CarbonImmutable::parse('2026-09-28'));

    expect(KpiDigest::query()->count())->toBe(1)
        ->and($resumen->sent_at)->toBeNull()
        ->and($resumen->error)->toContain('TELEGRAM_BOT_TOKEN')
        ->and($resumen->period_start->toDateString())->toBe('2026-09-28');

    Http::assertNothingSent();
});

it('el comando del resumen avisa cuando no pudo enviar', function () {
    config(['kpis.telegram.bot_token' => null]);

    $this->artisan('kpis:resumen', ['tipo' => 'diario'])->assertFailed();
    $this->artisan('kpis:resumen', ['tipo' => 'mensual'])->assertExitCode(2);
});
