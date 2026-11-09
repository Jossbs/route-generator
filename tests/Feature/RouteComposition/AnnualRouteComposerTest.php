<?php

declare(strict_types=1);

use App\Enums\RouteItemRole;
use App\Enums\RouteLayer;
use App\Models\ContentUnit;
use App\Models\Course;
use App\Models\Specialization;
use App\Services\RouteComposition\ComposerRegistry;
use App\Services\RouteComposition\Exceptions\CompositionException;
use App\Services\RouteValidation\Data\RouteDraft;
use App\Services\RouteValidation\Data\RouteItemDraft;
use Tests\Support\CompositionFactory;

/*
 * La ruta anual: la cortesía posterior a la compra.
 *
 * Son dos capas con reglas distintas. El itinerario recomienda y por eso no puede
 * llevar dos cursos que se traslapan; el inventario solo enumera, y ahí sí
 * aparecen los tres liderazgos porque el miembro efectivamente los tiene.
 */

function composeAnual(string $anchorSlug, bool $withMembership = true): RouteDraft
{
    $request = CompositionFactory::anual($anchorSlug, withMembership: $withMembership);

    return app(ComposerRegistry::class)->for($request->documentType)->compose($request);
}

/** @return list<RouteItemDraft> */
function itinerary(RouteDraft $draft): array
{
    return $draft->itemsInLayer(RouteLayer::Itinerario);
}

it('exige los datos de membresía antes de componer nada', function () {
    expect(fn () => composeAnual('lean', withMembership: false))
        ->toThrow(CompositionException::class, 'exige los datos de la membresía');
});

it('reparte el itinerario en doce meses', function () {
    $months = collect(itinerary(composeAnual('lean')))->pluck('month')->unique()->sort()->values();

    expect($months->all())->toBe(range(1, 12));
});

it('abre el mes 1 con la especialización que el lead pidió', function (string $anchorSlug) {
    $draft = composeAnual($anchorSlug);
    $anchor = Specialization::where('slug', $anchorSlug)->sole();

    $primerMes = collect(itinerary($draft))->where('month', 1)->sortBy('position');

    expect($primerMes->first()->specializationId)->toBe(
        $anchor->id,
        "El mes 1 no arranca con «{$anchor->name}», que es lo que el lead pidió.",
    );
})->with(['lean', 'sixsigma', 'coretools', 'soft', 'suministros']);

it('balancea las horas para que ningún mes doble a otro', function () {
    $draft = composeAnual('lean');

    $hours = Course::whereIn('id', $draft->courseIds())->pluck('duration_hours', 'id');

    $porMes = array_fill(1, 12, 0);
    foreach (itinerary($draft) as $item) {
        $start = $item->month;
        $end = $item->monthEnd ?? $start;
        $span = $end - $start + 1;
        $duration = $hours[$item->courseId];

        // Un curso largo también ocupa sus meses de continuación. Cargar todas
        // sus horas al mes inicial daría una falsa impresión de desequilibrio.
        for ($month = $start; $month <= $end; $month++) {
            $porMes[$month] += intdiv($duration, $span) + ($month - $start < $duration % $span ? 1 : 0);
        }
    }

    // Un mes de noventa horas junto a uno de doce no es una recomendación creíble
    // para alguien que estudia después del trabajo.
    expect(array_sum($porMes))->toBe((int) $hours->sum());
    expect(max($porMes))->toBeLessThanOrEqual((int) (min($porMes) * 2.5));
});

it('recomienda un solo curso de cada grupo que se traslapa', function () {
    $draft = composeAnual('sixsigma');

    $recomendados = collect(itinerary($draft))
        ->filter(fn (RouteItemDraft $i): bool => $i->role->isRecommendation())
        ->pluck('courseId');

    $liderazgo = Course::whereIn('slug', [
        'liderazgo-para-ingenieros', 'liderazgo-para-lideres-y-supervisores', 'liderazgo-efectivo',
    ])->pluck('id');

    $dmaic = Course::whereIn('slug', ['dmaic', 'green-belt-six-sigma', 'black-belt-six-sigma'])->pluck('id');

    expect($recomendados->intersect($liderazgo))->toHaveCount(1);
    expect($recomendados->intersect($dmaic))->toHaveCount(1);
});

it('lista en el inventario los cursos que el itinerario no recomienda', function () {
    $draft = composeAnual('lean');

    $inventario = collect($draft->itemsInLayer(RouteLayer::Inventario))->pluck('courseId');

    // El inventario enumera la membresía completa: los 64 cursos, incluidos los
    // traslapes que el itinerario descartó.
    expect($inventario->unique())->toHaveCount(64);

    $descartados = $inventario->diff(collect(itinerary($draft))->pluck('courseId'));
    expect($descartados)->not->toBeEmpty();
    expect($inventario)->toContain(Course::where('slug', 'ppap')->sole()->id);
});

it('marca el inventario como incluido y no como recomendación', function () {
    $draft = composeAnual('lean');

    $roles = collect($draft->itemsInLayer(RouteLayer::Inventario))->pluck('role')->unique();

    expect($roles->all())->toBe([RouteItemRole::Incluido]);
});

it('declara las cifras del catálogo completo: 64 cursos y 55 programas', function () {
    $draft = composeAnual('lean');

    expect($draft->declaredCourseCount)->toBe(64);
    expect($draft->declaredContentUnitCount)->toBe(55);
});

it('da a cada especialización de cada mes su propio encabezado', function () {
    $draft = composeAnual('kaizen');

    foreach ($draft->headings as $heading) {
        expect($heading->specializationIds)->toHaveCount(1);
        expect($heading->month)->not->toBeNull();
    }

    // Dos especializaciones pueden compartir un mes; cada una bajo su propio título.
    $claves = collect($draft->headings)->pluck('key');

    expect($claves->unique())->toHaveCount($claves->count());
});

it('no le da rol de ancla a Cadena de Suministros ni cuando el lead la pidió', function () {
    $draft = composeAnual('suministros');
    $suministros = Specialization::where('slug', 'suministros')->sole();

    $items = collect(itinerary($draft))->where('specializationId', $suministros->id);

    expect($items)->not->toBeEmpty();
    expect($items->pluck('role')->unique()->all())->toBe([RouteItemRole::Refuerzo]);

    // Conserva su bloque y su encabezado propio: es una especialidad y así se presenta.
    expect(collect($draft->headings)->pluck('specializationIds')->flatten())
        ->toContain($suministros->id);
});

it('condensa cada familia «Completo» en vez de desglosarla', function () {
    $draft = composeAnual('industrial');

    $enRuta = Course::whereIn('id', collect(itinerary($draft))->pluck('courseId'))
        ->whereIn('content_unit_id', ContentUnit::whereIn('slug', ['autocad', 'solidworks', 'microsoft-excel'])->pluck('id'))
        ->pluck('slug')
        ->sort()
        ->values()
        ->all();

    // Un renglón por familia, y las tres iguales: el documento no puede mostrar
    // SolidWorks desglosado en tres niveles y AutoCAD condensado en uno.
    expect($enRuta)->toBe(['autocad-completo', 'excel-completo', 'solidworks-completo']);
});

it('produce la misma ruta anual en dos corridas', function () {
    $primera = composeAnual('coretools');
    $segunda = composeAnual('coretools');

    expect(array_map(fn ($i): array => $i->toArray(), $primera->items))
        ->toBe(array_map(fn ($i): array => $i->toArray(), $segunda->items));
});

it('propone rutas anuales que el validador aprueba, para cada especialización pedida', function () {
    foreach (Specialization::pluck('slug') as $slug) {
        $report = validateDraft(composeAnual($slug));

        expect($report->passes())->toBeTrue(
            "La ruta anual pedida desde «{$slug}» fue rechazada: ".implode(' · ', $report->blockingMessages()),
        );
    }
});
