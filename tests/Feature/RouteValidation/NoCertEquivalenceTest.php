<?php

declare(strict_types=1);

use App\Enums\RouteItemRole;
use App\Services\RouteValidation\Data\RouteDraft;
use Tests\Support\DraftBuilder;

/*
 * Restricción 5 (docs/ARQUITECTURA.md §4.5) — NO_CERT_EQUIVALENCE.
 *
 * Sin afirmaciones de equivalencia de certificación y sin anclas de precio. El PDF
 * cuantifica horas, programas y constancias DC-3; el precio lo dice el asesor en la
 * conversación, donde puede manejar objeciones.
 */

function sixSigmaDraft(string ...$copy): RouteDraft
{
    return DraftBuilder::enfocada()
        ->anchor('sixsigma')
        ->course('green-belt-six-sigma', RouteItemRole::Ancla)
        ->course('dmaic')
        ->copy(...$copy)
        ->build();
}

it('rechaza afirmar equivalencia con una certificación de mercado', function () {
    $report = validateDraft(sixSigmaDraft('Tu Green Belt equivale a la certificación internacional.'));

    expect($report)->toFailRule('NO_CERT_EQUIVALENCE');
    expect($report->for('NO_CERT_EQUIVALENCE')->details['violations'][0]['scope'])->toBe('certificacion');
});

it('rechaza la comparación de precio con cifra', function () {
    expect(validateDraft(sixSigmaDraft('Cuesta 6 veces más barato que un Green Belt tradicional.')))
        ->toFailRule('NO_CERT_EQUIVALENCE');
});

it('rechaza la comparación de precio sin cifra', function () {
    expect(validateDraft(sixSigmaDraft('Es más barato que cualquier certificación comparable.')))
        ->toFailRule('NO_CERT_EQUIVALENCE');
});

it('rechaza cualquier ancla de precio', function () {
    $report = validateDraft(sixSigmaDraft('Tu membresía anual: $4,199 MXN.'));

    expect($report)->toFailRule('NO_CERT_EQUIVALENCE');
    expect(array_column($report->for('NO_CERT_EQUIVALENCE')->details['violations'], 'scope'))
        ->toContain('precio');
});

it('admite el fraseo de cumplimiento que cuantifica lo que sí hay', function () {
    expect(validateDraft(sixSigmaDraft(
        'Six Sigma suma 128 horas de contenido en cinco programas.',
        'Cada curso cierra con constancia DC-3 oficial ante la STPS y diploma verificable.',
    )))->toPassRule('NO_CERT_EQUIVALENCE');
});

it('se omite cuando el borrador todavía no trae copy ensamblado', function () {
    expect(validateDraft(sixSigmaDraft()))->toSkipRule('NO_CERT_EQUIVALENCE');
});
