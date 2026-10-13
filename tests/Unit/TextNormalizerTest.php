<?php

declare(strict_types=1);

use App\Services\Leads\TextNormalizer;

/*
 * Lo capturado, escrito como se escribe en español, venga como venga el teclado.
 */

it('escribe los nombres de persona con mayúscula en cada nombre y las partículas en minúscula', function (string $capturado, string $esperado) {
    expect((new TextNormalizer)->personName($capturado))->toBe($esperado);
})->with([
    'todo en mayúsculas' => ['MARIANA CRUZ AGUILAR', 'Mariana Cruz Aguilar'],
    'todo en minúsculas' => ['mariana cruz aguilar', 'Mariana Cruz Aguilar'],
    'bloqueo invertido' => ['mARIANA cRUZ', 'Mariana Cruz'],
    'partículas' => ['MARÍA JOSÉ DE LA O', 'María José de la O'],
    'partícula al inicio' => ['de la fuente ana', 'De la Fuente Ana'],
    'apellido compuesto' => ['ana pérez-gómez', 'Ana Pérez-Gómez'],
    'espacios de sobra' => ['  luis   ángel  ', 'Luis Ángel'],
]);

it('escribe las frases con mayúscula inicial y conserva las siglas', function (string $capturado, string $esperado) {
    expect((new TextNormalizer)->sentence($capturado))->toBe($esperado);
})->with([
    'todo en mayúsculas' => ['INGENIERA DE PROCESOS EN UNA AUTOMOTRIZ', 'Ingeniera de procesos en una automotriz'],
    'todo en minúsculas' => ['ingeniera de procesos', 'Ingeniera de procesos'],
    'mayúscula inicial en cada palabra' => ['Supervisor De Producción', 'Supervisor de producción'],
    'siglas' => ['gerente de TI en GM', 'Gerente de TI en GM'],
    'siglas en mayúscula inicial' => ['Jefe De Mantenimiento CNC', 'Jefe de mantenimiento CNC'],
    'nombre propio escrito a propósito' => ['ingeniero de calidad en Nissan', 'Ingeniero de calidad en Nissan'],
    'palabra gritada suelta' => ['Coordinadora de CALIDAD', 'Coordinadora de calidad'],
    'bloqueo invertido' => ['iNGENIERO de procesos', 'Ingeniero de procesos'],
]);

it('deja vacío lo que no trae texto', function () {
    expect((new TextNormalizer)->personName('   '))->toBeNull();
    expect((new TextNormalizer)->sentence(null))->toBeNull();
});
