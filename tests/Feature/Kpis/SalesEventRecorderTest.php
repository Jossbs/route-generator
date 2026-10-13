<?php

declare(strict_types=1);

use App\Enums\Kpis\SalesEventSource;
use App\Enums\Kpis\SalesEventStatus;
use App\Models\KpiPeriodClosure;
use App\Models\SalesEvent;
use App\Models\User;
use App\Services\Kpis\SalesEventRecorder;
use App\Services\Kpis\Sources\SuggestedEvent;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Tests\Support\KpiFactory;

/*
 * La captura: los campos se validan según su tipo, nada se borra y un mes
 * cerrado solo acepta ajustes con motivo (decidido el 2026-09-26).
 */

beforeEach(function () {
    $this->recorder = app(SalesEventRecorder::class);
    $this->venta = KpiFactory::saleType();
    $this->admin = User::factory()->create();
    $this->ana = User::factory()->create();
});

function cerrarMes(string $inicio): void
{
    KpiPeriodClosure::create(['period_start' => $inicio, 'snapshot' => [], 'closed_at' => now()]);
}

it('guarda los números como número y descarta lo que no es un campo', function () {
    $evento = $this->recorder->record(
        $this->venta, $this->ana, CarbonImmutable::parse('2026-09-10'),
        ['plan' => 'expert_anual', 'importe' => '4199.50', 'cantidad' => '1', 'otro' => 'x'],
        $this->admin,
    );

    expect($evento->values)->toBe(['plan' => 'expert_anual', 'importe' => 4199.5, 'cantidad' => 1])
        ->and($evento->status)->toBe(SalesEventStatus::Confirmado)
        ->and($evento->created_by)->toBe($this->admin->id);
});

it('rechaza campos obligatorios vacíos y opciones que no existen', function (array $valores, string $campo) {
    expect(fn () => $this->recorder->record($this->venta, $this->ana, CarbonImmutable::parse('2026-09-10'), $valores, $this->admin))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey($campo));
})->with([
    'sin importe' => [['plan' => 'expert_anual'], 'values.importe'],
    'importe no numérico' => [['plan' => 'expert_anual', 'importe' => 'mucho'], 'values.importe'],
    'plan inventado' => [['plan' => 'vitalicio', 'importe' => 10], 'values.plan'],
]);

it('en un mes cerrado exige motivo y marca el ajuste', function () {
    cerrarMes('2026-08-01');

    expect(fn () => $this->recorder->record($this->venta, $this->ana, CarbonImmutable::parse('2026-08-20'), ['plan' => 'duo', 'importe' => 5999], $this->admin))
        ->toThrow(ValidationException::class);

    $evento = $this->recorder->record(
        $this->venta, $this->ana, CarbonImmutable::parse('2026-08-20'),
        ['plan' => 'duo', 'importe' => 5999], $this->admin, adjustmentReason: 'Venta capturada tarde',
    );

    expect($evento->is_adjustment)->toBeTrue()
        ->and($evento->adjustment_reason)->toBe('Venta capturada tarde');
});

it('mover un evento hacia un mes cerrado también es un ajuste', function () {
    cerrarMes('2026-08-01');
    $evento = KpiFactory::sale($this->venta, $this->ana, '2026-09-02');

    expect(fn () => $this->recorder->update($evento->load('type.fields'), $this->ana, CarbonImmutable::parse('2026-08-31'), ['plan' => 'duo', 'importe' => 1], null))
        ->toThrow(ValidationException::class);
});

it('anular no borra: deja quién, cuándo y por qué', function () {
    $evento = KpiFactory::sale($this->venta, $this->ana, '2026-09-02');

    $this->recorder->void($evento, $this->admin, 'Duplicado');

    expect(SalesEvent::query()->count())->toBe(1)
        ->and($evento->fresh()->voided_by)->toBe($this->admin->id)
        ->and($evento->fresh()->void_reason)->toBe('Duplicado')
        ->and(SalesEvent::query()->counting()->count())->toBe(0);
});

it('una sugerencia se registra una sola vez y no pisa lo que el administrador ya decidió', function () {
    $sugerencia = fn (float $importe) => new SuggestedEvent(
        SalesEventSource::Kommo, 'deal:77', 'venta', $this->ana->id, CarbonImmutable::parse('2026-09-05'), ['plan' => 'expert_anual', 'importe' => $importe],
    );

    $primera = $this->recorder->suggest($sugerencia(4199));
    $this->recorder->suggest($sugerencia(3999));

    expect(SalesEvent::query()->count())->toBe(1)
        ->and($primera->fresh()->status)->toBe(SalesEventStatus::Sugerido)
        ->and($primera->fresh()->values['importe'])->toEqual(3999);

    $this->recorder->confirm($primera->fresh(), $this->admin);
    $this->recorder->suggest($sugerencia(1));

    expect($primera->fresh()->values['importe'])->toEqual(3999)
        ->and($primera->fresh()->status)->toBe(SalesEventStatus::Confirmado);
});

it('no sugiere nada si el tipo de evento no existe o está apagado', function () {
    $this->venta->update(['is_active' => false]);

    $resultado = $this->recorder->suggest(new SuggestedEvent(
        SalesEventSource::Rutas, 'membership:1', 'venta', $this->ana->id, CarbonImmutable::parse('2026-09-05'), [],
    ));

    expect($resultado)->toBeNull()->and(SalesEvent::query()->count())->toBe(0);
});
