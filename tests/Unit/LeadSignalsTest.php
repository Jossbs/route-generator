<?php

declare(strict_types=1);

use App\Enums\LeadArea;
use App\Models\Lead;
use App\Services\RouteComposition\Support\LeadSignals;

/*
 * Lo que la captura dice del lead.
 *
 * El área elegida de la lista manda: es el único dato tipificado y el asesor la
 * escogió. Lo que se prueba aquí es el caso de al lado —la que escribió a mano
 * porque ninguna de las catorce le quedaba— que antes dejaba al motor sin señal y
 * hacía que un lead de «Control de Gestión» recibiera lo mismo que uno sin área.
 */

it('prefiere lo que declara el área de la lista', function () {
    $signals = LeadSignals::from('Inspector', null, LeadArea::Calidad);

    expect($signals->preferredSpecializations())->toBe(LeadArea::Calidad->specializationSlugs());
});

it('da recomendaciones distintas a supervisión y mejora continua', function () {
    expect(LeadArea::Supervisor->specializationSlugs())->toContain('soft', 'lean')
        ->and(LeadArea::MejoraContinua->specializationSlugs())->toContain('sixsigma', 'kaizen')
        ->and(LeadArea::Supervisor->specializationSlugs())->not->toBe(LeadArea::MejoraContinua->specializationSlugs());
});

it('recupera el área de leads históricos escrita a mano', function () {
    $lead = new Lead(['area_other' => 'Área de calidad']);
    $signals = LeadSignals::of($lead);

    expect($signals->areaLabel())->toBe('Calidad / auditoría')
        ->and($signals->preferredSpecializations())->toBe(LeadArea::Calidad->specializationSlugs());
});

it('lee los temas del área escrita a mano', function () {
    $signals = LeadSignals::from('Analista', null, null, 'Control de Gestión y Presupuestos');

    expect($signals->preferredSpecializations())->toContain('excel');
});

it('suma lo que diga la ocupación y el contexto cuando el área es nueva', function () {
    $signals = LeadSignals::from(
        'Coordinadora de auditorías internas',
        'Quiere certificar la planta',
        null,
        'Control de Gestión',
    );

    expect($signals->preferredSpecializations())->toContain('iso');
});

it('no inventa afinidad cuando no hay nada que leer', function () {
    // Un área nueva que no se parece a ningún tema del catálogo no produce
    // candidata: deciden la industria y el puesto, como antes de que el campo
    // existiera. Fabricar una coincidencia mandaría una ruta pensada para otro.
    $signals = LeadSignals::from('Chofer', null, null, 'Transporte Foráneo');

    expect($signals->preferredSpecializations())->toBe([]);
});

it('nombra el área escrita a mano en la justificación que lee el asesor', function () {
    $signals = LeadSignals::from('Analista', null, null, 'Control de Gestión');

    expect($signals->areaLabel())->toBe('Control de Gestión');
});

it('deja el área vacía cuando no se capturó ninguna', function () {
    $signals = LeadSignals::from('Analista', null);

    expect($signals->preferredSpecializations())->toBe([])
        ->and($signals->areaLabel())->toBeNull();
});

it('usa el objetivo del lead aunque no haya un área capturada', function () {
    expect(LeadSignals::from(null, 'Quiero aprender Power BI para analizar datos')->preferredSpecializations())
        ->toContain('datos');
});

it('usa la ocupación del lead aunque no haya un área capturada', function () {
    expect(LeadSignals::from('Analista de presupuestos', null)->preferredSpecializations())
        ->toContain('excel');
});
