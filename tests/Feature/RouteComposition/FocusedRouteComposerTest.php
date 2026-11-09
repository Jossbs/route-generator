<?php

declare(strict_types=1);

use App\Enums\RouteItemRole;
use App\Enums\RouteLayer;
use App\Models\Course;
use App\Models\Specialization;
use App\Services\RouteComposition\ComposerRegistry;
use App\Services\RouteComposition\Exceptions\CompositionException;
use App\Services\RouteValidation\Data\RouteDraft;
use App\Services\RouteValidation\Data\RouteItemDraft;
use Tests\Support\CompositionFactory;

/*
 * La ruta enfocada: el gancho que se manda por WhatsApp antes de la venta.
 *
 * La prueba que gobierna a todas es la última de este archivo: toda ruta que el
 * compositor proponga tiene que pasar el validador. Si alguna especialización
 * ancla produce un documento que las restricciones rechazan, el asesor se entera
 * con el lead esperando del otro lado.
 */

function composeEnfocada(string $anchorSlug): RouteDraft
{
    $request = CompositionFactory::enfocada($anchorSlug);

    return app(ComposerRegistry::class)->for($request->documentType)->compose($request);
}

/** @return list<RouteItemDraft> */
function itemsWithRole(RouteDraft $draft, RouteItemRole $role): array
{
    return array_values(array_filter(
        $draft->items,
        fn (RouteItemDraft $item): bool => $item->role === $role,
    ));
}

it('lleva la especialización que pidió el lead completa, como ancla', function () {
    $draft = composeEnfocada('coretools');

    $anchor = Specialization::where('slug', 'coretools')->sole();
    $anchorItems = itemsWithRole($draft, RouteItemRole::Ancla);

    expect($draft->anchorSpecializationId)->toBe($anchor->id);
    expect($anchorItems)->toHaveCount(4); // los cuatro cursos que Core Tools publica

    $slugs = Course::whereIn('id', array_map(fn ($i): int => $i->courseId, $anchorItems))
        ->pluck('slug')->sort()->values()->all();

    expect($slugs)->toBe(['amef', 'plan-de-control', 'ppap', 'spc']);
});

it('acompaña la ancla con dos complementarias', function () {
    $draft = composeEnfocada('coretools');

    $complementarySpecializations = collect(itemsWithRole($draft, RouteItemRole::Complementaria))
        ->pluck('specializationId')
        ->unique();

    expect($complementarySpecializations)->toHaveCount(2);
    expect($complementarySpecializations)->not->toContain($draft->anchorSpecializationId);
});

it('agrega exactamente tres cursos de refuerzo', function () {
    expect(itemsWithRole(composeEnfocada('coretools'), RouteItemRole::Complementaria))->toHaveCount(3);
});

it('deja el documento dentro del rango esperado, sea cual sea el ancla', function (string $anchorSlug) {
    $draft = composeEnfocada($anchorSlug);

    // 8 a 14 es la expectativa de docs/ARQUITECTURA.md §8, medida en renglones, que es lo que
    // el lead cuenta al abrir el documento. Una ancla grande puede rebasarla —Lean
    // son once cursos y Soft Skills trece—, pero el documento no debe dispararse:
    // eso dejaría de ser una ruta y pasaría a ser un catálogo.
    //
    // El piso se deriva en vez de fijarse en 8 porque hay anclas a las que el
    // catálogo no le da para tanto: con Normas ISO o Análisis de Datos, que tienen
    // dos cursos, y con Kaizen rindiendo dos de sus cuatro —5'S y OEE se esquivan
    // por el traslape sospechado—, el máximo honesto son siete. Exigir ocho ahí
    // obligaría a meter un curso que el guard acaba de apartar.
    $anchorCourses = collect($draft->items)
        ->where('specializationId', $draft->anchorSpecializationId)
        ->count();

    expect($draft->declaredCourseCount)
        ->toBeGreaterThanOrEqual($anchorCourses + 3)
        ->toBeLessThanOrEqual(18);
})->with(['coretools', 'lean', 'sixsigma', 'excel', 'iso', 'datos', 'kaizen', 'soft', 'industrial', 'calidad', 'problemas']);

it('nunca propone Cadena de Suministros como ancla', function () {
    // Un solo curso: quedaría un bloque de uno junto a otros de ocho o dieciséis.
    // No se sustituye en silencio; el asesor tiene que enterarse.
    expect(fn () => composeEnfocada('suministros'))
        ->toThrow(CompositionException::class, 'no puede ser la especialización principal');
});

it('nunca propone Cadena de Suministros como complementaria', function () {
    $suministros = Specialization::where('slug', 'suministros')->sole();

    foreach (['coretools', 'lean', 'sixsigma', 'calidad'] as $anchorSlug) {
        $draft = composeEnfocada($anchorSlug);

        expect($draft->specializationIds())->not->toContain($suministros->id);
    }
});

it('no recomienda dos cursos que se traslapan', function () {
    $draft = composeEnfocada('soft');

    $liderazgos = Course::whereIn('slug', [
        'liderazgo-para-ingenieros',
        'liderazgo-para-lideres-y-supervisores',
        'liderazgo-efectivo',
    ])->pluck('id');

    expect(collect($draft->courseIds())->intersect($liderazgos))->toHaveCount(1);
});

it('no mete un paquete «Completo» junto a sus propios niveles', function () {
    $draft = composeEnfocada('excel');

    $excel = Course::whereIn('slug', ['excel-completo', 'excel-basico', 'excel-intermedio', 'excel-avanzado'])
        ->pluck('slug', 'id');

    $present = $excel->only($draft->courseIds());

    expect($present->contains('excel-completo') && $present->count() > 1)->toBeFalse(
        'La ruta lleva Excel Completo junto a alguno de sus niveles: '.$present->implode(', '),
    );
});

it('deja fuera del itinerario los cursos sin duración publicada', function () {
    $draft = composeEnfocada('soft');

    $sinDuracion = Course::whereNull('duration_hours')->pluck('id');

    expect(collect($draft->courseIds())->intersect($sinDuracion))->toBeEmpty();
});

it('da a cada especialización su propio encabezado', function () {
    $draft = composeEnfocada('lean');

    expect($draft->headings)->toHaveCount(3); // la ancla más dos complementarias

    foreach ($draft->headings as $heading) {
        expect($heading->specializationIds)->toHaveCount(1);
    }
});

it('declara las dos cifras que el documento imprime', function () {
    $draft = composeEnfocada('excel');

    expect($draft->declaredCourseCount)->toBe(count($draft->courseIds()));
    expect($draft->declaredContentUnitCount)->toBeLessThanOrEqual($draft->declaredCourseCount);
});

it('presenta el itinerario en orden pedagógico dentro de cada especialización', function () {
    $draft = composeEnfocada('excel');

    $levels = Course::whereIn('id', $draft->courseIds())->pluck('complexity_level', 'id');

    collect($draft->items)
        ->groupBy('specializationId')
        ->each(function ($items) use ($levels) {
            $sequence = $items->sortBy('position')->map(fn ($i): int => $levels[$i->courseId])->values()->all();

            expect($sequence)->toBe(collect($sequence)->sort()->values()->all());
        });
});

it('arma solo capa de itinerario: el gancho no lleva inventario', function () {
    $draft = composeEnfocada('lean');

    expect($draft->itemsInLayer(RouteLayer::Inventario))->toBeEmpty();
    expect($draft->itemsInLayer(RouteLayer::Itinerario))->not->toBeEmpty();
});

it('produce la misma ruta en dos corridas', function () {
    // Sin desempate estable, dos cursos del mismo peso saldrían en el orden que
    // devuelva PostgreSQL y el mismo lead recibiría documentos distintos.
    $primera = composeEnfocada('calidad');
    $segunda = composeEnfocada('calidad');

    expect(array_map(fn ($i): array => $i->toArray(), $primera->items))
        ->toBe(array_map(fn ($i): array => $i->toArray(), $segunda->items));
});

it('propone rutas que el validador aprueba, para cada ancla posible', function () {
    $anchors = Specialization::all()
        ->filter(fn (Specialization $s): bool => $s->allowsRole('ancla'))
        ->pluck('slug');

    expect($anchors)->toHaveCount(11); // las 12 menos Cadena de Suministros

    foreach ($anchors as $slug) {
        $report = validateDraft(composeEnfocada($slug));

        expect($report->passes())->toBeTrue(
            "La ruta con ancla «{$slug}» fue rechazada: ".implode(' · ', $report->blockingMessages()),
        );
    }
});
