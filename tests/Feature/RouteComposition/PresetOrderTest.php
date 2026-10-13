<?php

declare(strict_types=1);

use App\Models\DocumentType;
use App\Models\Specialization;
use App\Services\RouteComposition\Support\PresetDraftBuilder;
use App\Services\RouteValidation\Data\HeadingDraft;
use App\Services\RouteValidation\Data\RouteDraft;
use Database\Seeders\DocumentTypeSeeder;

/*
 * El orden que deja el asesor.
 *
 * La pantalla de propuesta no es una vista de solo lectura: el asesor acomoda
 * los cursos y los bloques antes de generar, porque el orden que el motor
 * propone es una recomendación y no un absoluto. Lo que sostiene esa pantalla no
 * es código de pantalla, es esto: la lista llega en el orden en que se va a leer
 * y `position` la conserva tal cual hasta el documento.
 *
 * Si esto se rompe, el documento sale en un orden que el asesor no eligió y
 * nadie se entera hasta que el lead lo abre.
 */

function preset(array $courseIds, ?int $anchorSpecializationId = null): RouteDraft
{
    return app(PresetDraftBuilder::class)->build(
        documentType: DocumentType::query()->where('slug', DocumentTypeSeeder::ENFOCADA)->sole(),
        courseIds: $courseIds,
        anchorSpecializationId: $anchorSpecializationId,
    );
}

/** @return list<string> */
function headingLabels(RouteDraft $draft): array
{
    return array_map(fn (HeadingDraft $heading): string => $heading->label, $draft->headings);
}

it('presenta los cursos en el orden en que el asesor los dejó', function () {
    $ids = [
        courseId('green-belt-six-sigma'),
        courseId('white-belt-six-sigma'),
        courseId('yellow-belt-six-sigma'),
    ];

    $draft = preset($ids);

    expect($draft->courseIds())->toBe($ids);
    expect(array_map(fn ($item): int => $item->position, $draft->items))->toBe([1, 2, 3]);
});

it('cambia el orden de los bloques cuando cambia el de sus cursos', function () {
    $sixSigma = courseId('white-belt-six-sigma');
    $lean = courseId('ocho-desperdicios');

    expect(headingLabels(preset([$sixSigma, $lean])))
        ->toBe(['Six Sigma', 'Lean Manufacturing']);

    // El mismo par de cursos, movido el bloque: el documento los lee al revés.
    expect(headingLabels(preset([$lean, $sixSigma])))
        ->toBe(['Lean Manufacturing', 'Six Sigma']);
});

it('no repite un curso que ya está en la ruta principal', function () {
    // La especialidad por la que preguntó el lead ya trae sus cursos; volver a
    // agregar uno no lo duplica en la hoja.
    $repetido = courseId('white-belt-six-sigma');

    $draft = preset([$repetido, courseId('yellow-belt-six-sigma'), $repetido]);

    expect($draft->courseIds())->toBe([$repetido, courseId('yellow-belt-six-sigma')]);
});

it('mantiene de ancla a la especialidad que el lead pidió, aunque se reordene', function () {
    $sixSigma = (int) Specialization::query()->where('slug', 'sixsigma')->value('id');

    $draft = preset([courseId('ocho-desperdicios'), courseId('white-belt-six-sigma')], $sixSigma);

    expect($draft->anchorSpecializationId)->toBe($sixSigma);
});
