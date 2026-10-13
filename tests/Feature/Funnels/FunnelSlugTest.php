<?php

declare(strict_types=1);

use App\Models\Funnel;
use Tests\Support\FunnelFactory;

/*
 * El identificador de la ruta curada.
 *
 * Ya no se captura ni sigue convención: se deriva del nombre. Quien arma la ruta
 * escribe «Calidad» y no tiene por qué inventar un formato. Lo que sí importa es
 * que sea único y que no se mueva: es la llave con la que el snapshot de cada
 * documento emitido se refiere a esta ruta.
 */

it('deriva el identificador del nombre', function () {
    expect(Funnel::uniqueSlugFor('Calidad y Mejora Continua'))
        ->toBe('calidad-y-mejora-continua');

    expect(Funnel::uniqueSlugFor('Six Sigma — nivel básico'))
        ->toBe('six-sigma-nivel-basico');
});

it('desempata los homónimos con un sufijo', function () {
    FunnelFactory::funnel(['slug' => 'calidad']);

    expect(Funnel::uniqueSlugFor('Calidad'))->toBe('calidad-2');
});

it('cuenta también las rutas borradas al desempatar', function () {
    // Su slug sigue vivo en el snapshot de los documentos que ya salieron.
    $funnel = FunnelFactory::funnel(['slug' => 'calidad']);
    $funnel->delete();

    expect(Funnel::uniqueSlugFor('Calidad'))->toBe('calidad-2');
});

it('no se estorba a sí misma al recalcularse', function () {
    $funnel = FunnelFactory::funnel(['slug' => 'calidad']);

    expect(Funnel::uniqueSlugFor('Calidad', $funnel->id))->toBe('calidad');
});
