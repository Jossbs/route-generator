<?php

declare(strict_types=1);

use App\Enums\LeadArea;
use App\Services\Leads\AreaNormalizer;
use Illuminate\Support\Str;

/*
 * El área capturada, guardada de una sola manera.
 *
 * El asesor escribe rápido y con el lead esperando del otro lado. Lo que estas
 * pruebas defienden es que eso no se convierta en catorce formas de escribir la
 * misma área: sin normalizar, cualquier conteo por área deja de significar algo.
 */

function resolver(?string $area, ?string $otro = null)
{
    return (new AreaNormalizer)->resolve($area, $otro);
}

it('respeta lo que el asesor eligió de la lista', function () {
    $resuelta = resolver('calidad');

    expect($resuelta->area)->toBe(LeadArea::Calidad);
    expect($resuelta->other)->toBeNull();
    expect($resuelta->label())->toBe('Calidad / auditoría');
});

it('no guarda nada cuando no se capturó área', function () {
    expect(resolver(null)->area)->toBeNull();
    expect(resolver('')->other)->toBeNull();
    expect(resolver('otro', '   ')->label())->toBeNull();
});

it('reconoce la misma área escrita de cualquier forma', function (string $escrito) {
    expect(resolver('otro', $escrito)->area)->toBe(LeadArea::Calidad);
})->with([
    'Calidad',
    'CALIDAD',
    '  calidad  ',
    'Control de Calidad',
    'aseguramiento de calidad',
    'QA',
    'calidad.',
]);

it('perdona un error de dedo en cadenas largas', function () {
    expect(resolver('otro', 'mantenimeinto')->area)->toBe(LeadArea::Mantenimiento);
    expect(resolver('otro', 'recursos humanoss')->area)->toBe(LeadArea::RecursosHumanos);
});

it('no confunde dos palabras cortas parecidas', function () {
    // Una letra de diferencia en seis caracteres no es un error de dedo, es otra
    // palabra. Meter a un lead de rentas en Ventas le manda la ruta equivocada.
    expect(resolver('otro', 'rentas')->area)->toBeNull();
    expect(resolver('otro', 'rentas')->other)->toBe('Rentas');
});

it('lee el área dentro de una frase', function () {
    expect(resolver('otro', 'jefe de almacén')->area)->toBe(LeadArea::Logistica);
    expect(resolver('otro', 'Coordinador de seguridad e higiene')->area)->toBe(LeadArea::Seguridad);
    expect(resolver('otro', 'Analista de cadena de suministro')->area)->toBe(LeadArea::Compras);
    expect(resolver('otro', 'Supervisor de línea')->area)->toBe(LeadArea::Supervisor);
    expect(resolver('otro', 'Especialista de mejora continua')->area)->toBe(LeadArea::MejoraContinua);
});

it('quita los acentos para comparar, no para guardar', function () {
    // Conserva el código histórico `logistica`, cuya etiqueta ahora distingue Almacén.
    expect(resolver('otro', 'logistica')->area)->toBe(LeadArea::Logistica);
    expect(resolver('otro', 'LOGÍSTICA')->area)->toBe(LeadArea::Logistica);
    expect(resolver('otro', 'almacen')->area)->toBe(LeadArea::Logistica);
});

it('estandariza el área que no está en la lista', function () {
    // Tres asesores escribiendo lo mismo tienen que producir una sola fila.
    foreach (['CONTROL DE GESTION', 'control de gestion', 'Control De  Gestion '] as $escrito) {
        expect(resolver('otro', $escrito)->other)->toBe('Control de Gestion');
    }
});

it('conserva los acentos del área nueva', function () {
    // No se le puede adivinar el acento a una palabra que no está en ninguna
    // lista, así que se respeta lo que el asesor escribió.
    expect(resolver('otro', 'control de gestión')->other)->toBe('Control de Gestión');
});

it('ordena las áreas por etiqueta y deja otra hasta el final', function () {
    $labels = array_column(LeadArea::options(), 'label');
    $otra = array_pop($labels);
    $ordenadas = $labels;
    usort($ordenadas, fn (string $a, string $b): int => [
        Str::ascii(Str::lower($a)),
        $a,
    ] <=> [
        Str::ascii(Str::lower($b)),
        $b,
    ]);

    expect($labels[0])->toBe('Administración y Finanzas')
        ->and($labels[1])->toBe('Almacén / inventarios')
        ->and($labels)->toContain('Mejora continua', 'Supervisor / líder de línea')
        ->and($labels)->toBe($ordenadas)
        ->and($otra)->toBe('Otra — la escribo');
});

it('acepta el área tipificada aunque llegue por el campo abierto', function () {
    // Es lo que pasa cuando la captura viene por URL desde el CRM: ahí nadie
    // garantiza que mande uno de los catorce valores.
    expect(resolver('Recursos Humanos')->area)->toBe(LeadArea::RecursosHumanos);
    expect(resolver('mantenimiento')->area)->toBe(LeadArea::Mantenimiento);
});

it('no inventa un área que no reconoce', function () {
    $resuelta = resolver('otro', 'Investigación clínica');

    expect($resuelta->area)->toBeNull();
    expect($resuelta->other)->toBe('Investigación Clínica');
});

it('reutiliza la escritura de un área nueva que ya se guardó', function () {
    // Sin esto, «control de gestion» y «Control de Gestión» serían dos filas:
    // el acento no se adivina, pero sí se hereda de la que ya existe.
    $normalizador = new AreaNormalizer(fn (): array => ['Comercio Exterior', 'Control de Gestión']);

    expect($normalizador->resolve('otro', 'control de gestion')->other)->toBe('Control de Gestión');
    expect($normalizador->resolve('otro', 'Control de Gestoin')->other)->toBe('Control de Gestión');
    expect($normalizador->resolve('otro', 'Gestión del Cambio')->other)->toBe('Gestión del Cambio');
});
