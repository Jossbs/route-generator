<?php

declare(strict_types=1);

use App\Services\Leads\MexicanPhone;

/*
 * El WhatsApp del lead, guardado de una sola forma: es la llave del reencuentro.
 */

it('guarda el mismo número de la misma forma, se escriba como se escriba', function (string $capturado) {
    expect(MexicanPhone::normalize($capturado))->toBe('+524421185502');
})->with([
    'diez dígitos' => ['4421185502'],
    'con espacios' => ['442 118 55 02'],
    'con guiones' => ['442-118-5502'],
    'con lada' => ['+52 442 118 5502'],
    'con el 1 de WhatsApp' => ['+521 442 118 5502'],
]);

it('no inventa un número que no son diez dígitos', function (string $capturado) {
    expect(MexicanPhone::normalize($capturado))->toBeNull();
})->with(['442118550', '44211855021', 'sin número', '']);

it('se lee agrupado en la bandeja', function () {
    expect(MexicanPhone::display('+524421185502'))->toBe('+52 442 118 5502');
});
