<?php

declare(strict_types=1);

use App\Services\Documents\RouteDuration;

it('comparte la duración escrita y hablada sin fijar la del ejemplo', function (int $hours, string $written, string $spoken) {
    $payload = ['ruta' => ['totales' => ['horas_netas' => $hours]]];

    expect(RouteDuration::for($payload))->toBe($written)
        ->and(RouteDuration::for($payload, spoken: true))->toBe($spoken);
})->with([
    [15, 'una semana', 'una semana'],
    [75, '5 semanas', 'cinco semanas'],
    [135, '8 semanas', 'ocho semanas'],
    [165, '2 meses y medio', 'dos meses y medio'],
    [195, '2 meses y medio', 'dos meses y medio'],
    [225, '3 meses', 'tres meses'],
]);

it('usa el ritmo capturado cuando existe', function () {
    $payload = [
        'ruta' => ['totales' => ['horas_netas' => 225]],
        'membresia' => ['weekly_hours' => 5],
    ];

    expect(RouteDuration::for($payload))->toBe('10 meses y medio');
});

it('no inventa una duración si faltan las horas', function () {
    expect(RouteDuration::for([]))->toBeNull()
        ->and(RouteDuration::for(['ruta' => ['totales' => ['horas_netas' => 0]]]))->toBeNull();
});
