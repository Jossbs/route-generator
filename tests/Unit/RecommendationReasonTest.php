<?php

declare(strict_types=1);

use App\Services\Documents\RecommendationReason;

it('redacta el beneficio como una acción sin alterar el contenido del curso', function (string $frase, string $esperado) {
    $reason = (new RecommendationReason)->for(['frase_resultado' => $frase]);
    expect($reason)->toBe($esperado);
})->with([
    ['Analiza y resuelve problemas de calidad con datos', 'Te lo recomiendo para analizar y resolver problemas de calidad con datos.'],
    ['Consulta y administra bases de datos relacionales', 'Te lo recomiendo para consultar y administrar bases de datos relacionales.'],
    ['Dashboards ejecutivos y análisis para planta', 'Su enfoque complementa tu ruta: Dashboards ejecutivos y análisis para planta.'],
]);

it('no inventa resultados cuando el snapshot no trae descripción del curso', function () {
    expect((new RecommendationReason)->for(['especialidad' => 'Microsoft Excel']))
        ->toBe('Amplía tu formación en Microsoft Excel como complemento de tu ruta principal.');
});
