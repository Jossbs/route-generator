<?php

declare(strict_types=1);

use App\Models\Course;
use App\Services\Catalog\RouteTotals;

/*
 * Las horas del documento nunca se suman en bruto.
 *
 * Los tres paquetes «Completo» absorben a sus propios niveles, así que dentro de
 * una misma unidad de contenido manda la duración que el paquete declara. En
 * AutoCAD y SolidWorks la diferencia es de 12 h que el sitio no explica: se usa el
 * dato publicado del paquete, no se resuelve inventando.
 */

function totalsFor(array $slugs): RouteTotals
{
    return RouteTotals::for(Course::whereIn('slug', $slugs)->get());
}

it('reporta las horas del paquete y no la suma de sus niveles', function (string $package, array $components, int $expected) {
    // El paquete declara 36 h; sus tres niveles suman 48. Prometer 48 sería
    // prometer horas que la constancia no respalda.
    expect(totalsFor([$package])->netHours)->toBe($expected);
    expect(totalsFor([$package, ...$components])->netHours)->toBe($expected);
})->with([
    'AutoCAD' => ['autocad-completo', ['autocad-basico', 'autocad-intermedio', 'autocad-avanzado'], 36],
    'SolidWorks' => ['solidworks-completo', ['solidworks-basico', 'solidworks-intermedio', 'solidworks-avanzado'], 36],
    'Excel' => ['excel-completo', ['excel-basico', 'excel-intermedio', 'excel-avanzado'], 36],
]);

it('suma normal cuando los niveles van sin su paquete', function () {
    // Sin el paquete no hay nada que absorba: tres cursos, tres duraciones.
    expect(totalsFor(['autocad-basico', 'autocad-intermedio', 'autocad-avanzado'])->netHours)->toBe(48);
});

it('cuenta los cuatro cursos de un paquete como un solo programa', function () {
    // Es la contención que sostiene el «64 cursos · 55 programas» a escala de ruta.
    $totals = totalsFor(['excel-completo', 'excel-basico', 'excel-intermedio', 'excel-avanzado']);

    expect($totals->courseCount)->toBe(4);
    expect($totals->contentUnitCount)->toBe(1);
});

it('señala qué cursos quedaron absorbidos por un paquete presente', function () {
    $totals = totalsFor(['excel-completo', 'excel-basico']);

    expect($totals->absorbedCourseIds)->toBe(Course::where('slug', 'excel-basico')->pluck('id')->all());
});

it('no revienta con cursos sin duración publicada', function () {
    // Tres micro-cursos no publican horas. Cero es honesto; inventar un número no.
    expect(totalsFor(['mente-millonaria', 'liderazgo-efectivo'])->netHours)->toBe(0);
});

it('deduplica el mismo curso repetido en la ruta', function () {
    $courses = Course::whereIn('slug', ['spc'])->get();

    expect(RouteTotals::for($courses->concat($courses))->courseCount)->toBe(1);
});
