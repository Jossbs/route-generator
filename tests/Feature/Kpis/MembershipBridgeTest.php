<?php

declare(strict_types=1);

use App\Enums\Kpis\SalesEventSource;
use App\Enums\Kpis\SalesEventStatus;
use App\Enums\MembershipPlan;
use App\Models\Lead;
use App\Models\Membership;
use App\Models\SalesEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\KpiSeeder;

/*
 * El puente con el generador de rutas: cada membresía se sugiere como venta y
 * espera confirmación, para no capturar dos veces ni contar sin revisar
 * (decidido el 2026-09-26).
 */

beforeEach(function () {
    $this->seed(KpiSeeder::class);
    $this->asesor = User::factory()->create();
    $this->lead = Lead::create(['name' => 'Lead de prueba']);
});

function membresia(Lead $lead, User $asesor, array $extra = []): Membership
{
    return Membership::create([
        'lead_id' => $lead->id,
        'full_name' => 'María López',
        'occupation' => 'Ingeniera de calidad',
        'plan' => MembershipPlan::ExpertAnual,
        'price' => 4199,
        'starts_on' => '2026-09-10',
        'ends_on' => '2027-09-10',
        'created_by' => $asesor->id,
        ...$extra,
    ]);
}

it('sugiere una venta por membresía, acreditada a quien la registró', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 23:30:00', 'UTC')); // 17:30 en México
    $m = membresia($this->lead, $this->asesor);

    $evento = SalesEvent::sole();

    expect($evento->status)->toBe(SalesEventStatus::Sugerido)
        ->and($evento->source)->toBe(SalesEventSource::Rutas)
        ->and($evento->external_id)->toBe('membership:'.$m->id)
        ->and($evento->user_id)->toBe($this->asesor->id)
        ->and($evento->occurred_on->toDateString())->toBe('2026-09-10')
        ->and($evento->values)->toEqual(['plan' => 'expert_anual', 'importe' => 4199, 'cantidad' => 1]);
});

it('usa el día de México, no el de UTC', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-11 03:00:00', 'UTC')); // 21:00 del 10 en México
    membresia($this->lead, $this->asesor);

    expect(SalesEvent::sole()->occurred_on->toDateString())->toBe('2026-09-10');
});

it('una corrección de la membresía actualiza la sugerencia pendiente', function () {
    $m = membresia($this->lead, $this->asesor);
    $m->update(['plan' => MembershipPlan::Duo, 'price' => 5999]);

    expect(SalesEvent::query()->count())->toBe(1)
        ->and(SalesEvent::sole()->values['plan'])->toBe('duo')
        ->and(SalesEvent::sole()->values['importe'])->toEqual(5999);
});

it('una sugerencia no cuenta en el tablero hasta confirmarse', function () {
    membresia($this->lead, $this->asesor);

    expect(SalesEvent::query()->counting()->count())->toBe(0);
});

it('importar desde rutas no duplica lo ya sugerido', function () {
    membresia($this->lead, $this->asesor);

    $this->artisan('kpis:importar', ['fuente' => 'rutas'])->assertSuccessful();

    expect(SalesEvent::query()->count())->toBe(1);
});
