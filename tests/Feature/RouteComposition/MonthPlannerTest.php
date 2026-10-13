<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\DocumentType;
use App\Models\Specialization;
use App\Services\RouteComposition\Data\CompositionPolicy;
use App\Services\RouteComposition\Support\CourseRanker;
use App\Services\RouteComposition\Support\MonthPlanner;
use Database\Seeders\DocumentTypeSeeder;

/*
 * El reparto en meses, aislado del compositor.
 *
 * El itinerario anual condensa las familias «Completo», así que las cadenas de
 * prerrequisitos duros no llegan a aparecer en él. Eso no hace prescindible la
 * restricción: el día que una especialidad publique niveles sin paquete que los
 * agrupe, el planificador tiene que seguir sabiendo que Básico va antes.
 */

function planFor(array $courseSlugs, int $months = 12): array
{
    $courses = Course::whereIn('slug', $courseSlugs)->get()->keyBy('id');
    $policy = CompositionPolicy::for(DocumentType::where('slug', DocumentTypeSeeder::ANUAL)->sole());
    $order = Specialization::pluck('id')->all();

    return app(MonthPlanner::class)->distribute(
        $courses,
        $order,
        new CourseRanker($policy),
        $months,
    );
}

/** Mes en que quedó cada curso, por slug. */
function monthBySlug(array $plan): array
{
    $months = [];

    foreach ($plan as $month => $blocks) {
        foreach ($blocks as $courses) {
            foreach ($courses as $course) {
                $months[$course->slug] = $month;
            }
        }
    }

    return $months;
}

it('nunca coloca un nivel antes que el que lo precede', function () {
    $months = monthBySlug(planFor(['excel-basico', 'excel-intermedio', 'excel-avanzado']));

    expect($months['excel-basico'])->toBeLessThan($months['excel-intermedio']);
    expect($months['excel-intermedio'])->toBeLessThan($months['excel-avanzado']);
});

it('agrupa una cadena en meses consecutivos en vez de dispersarla', function () {
    $months = monthBySlug(planFor(['autocad-basico', 'autocad-intermedio', 'autocad-avanzado']));

    expect($months['autocad-avanzado'] - $months['autocad-basico'])->toBe(2);
});

it('no exige un prerrequisito que no está en el itinerario', function () {
    // Con la cadena presente, Avanzado espera a que pasen Básico e Intermedio.
    // Sin ella —porque un traslape la dejó fuera— arranca desde el mes 1: exigir
    // un curso que el documento no recomienda empujaría la ruta sin motivo.
    $conCadena = monthBySlug(planFor(['excel-basico', 'excel-intermedio', 'excel-avanzado']));
    $sinCadena = monthBySlug(planFor(['excel-avanzado']));

    expect($conCadena['excel-avanzado'])->toBe(3);
    expect($sinCadena['excel-avanzado'])->toBe(1);
});

it('deja lo que funda antes que lo que exige base previa', function () {
    $months = monthBySlug(planFor([
        'green-belt-six-sigma', 'white-belt-six-sigma', 'yellow-belt-six-sigma',
    ], months: 3));

    expect($months['white-belt-six-sigma'])->toBeLessThanOrEqual($months['yellow-belt-six-sigma']);
    expect($months['yellow-belt-six-sigma'])->toBeLessThanOrEqual($months['green-belt-six-sigma']);
});

it('devuelve un plan vacío cuando no hay nada que repartir', function () {
    expect(planFor([]))->toBe([]);
});

it('no inventa meses de más allá del límite', function () {
    $plan = planFor(['lean-manufacturing', 'smed', 'poka-yoke'], months: 2);

    expect(array_keys($plan))->each->toBeLessThanOrEqual(2);
});
