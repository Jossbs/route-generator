<?php

declare(strict_types=1);

use App\Enums\RouteLayer;
use App\Models\Course;
use App\Services\Catalog\ComplexityRubric;
use Database\Seeders\CatalogAnalysisSeeder;
use Database\Seeders\CatalogSeeder;

/*
 * La capa de análisis del catálogo contra el Diccionario de Datos v1.0.
 *
 * La prueba que importa es la primera: `ComplexityRubric` reimplementa en PHP la
 * rúbrica que `calc.py` corre en Python, y `cursos.json` es la salida de esa
 * corrida. Si las dos implementaciones se separan, el nivel deja de ser
 * reproducible y el criterio con que se ordena un itinerario deja de ser
 * defendible frente al lead.
 */

/** La corrida de referencia de calc.py, indexada por nombre normalizado. */
function referenceRun(): array
{
    static $rows = null;

    if ($rows === null) {
        $normalize = fn (string $name): string => trim(preg_replace(
            '/[^a-z0-9]+/',
            ' ',
            strtr(mb_strtolower($name), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']),
        ));

        $rows = [];
        foreach (json_decode(file_get_contents(base_path('cursos.json')), true) as $row) {
            $rows[$normalize($row['nombre'])] = $row;
        }

        // El Diccionario abrevia este nombre en la corrida; el catálogo lo escribe completo.
        $rows[$normalize('ISO 19011:2018 Auditorías de Sistemas de Gestión')] = $rows[$normalize('ISO 19011:2018 Auditorias de SG')];
    }

    return $rows;
}

it('reproduce el nivel de complejidad que calculó calc.py', function () {
    // La corrida histórica se verifica con los datos de septiembre, no con las
    // duraciones publicadas en noviembre que ahora usa la aplicación.
    $this->seed([CatalogSeeder::class, CatalogAnalysisSeeder::class]);
    $reference = referenceRun();
    $normalize = fn (string $name): string => trim(preg_replace(
        '/[^a-z0-9]+/',
        ' ',
        strtr(mb_strtolower($name), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']),
    ));

    $compared = 0;

    foreach (Course::whereNotNull('complexity_level')->get() as $course) {
        $expected = $reference[$normalize($course->name)] ?? null;

        expect($expected)->not->toBeNull("«{$course->name}» no aparece en la corrida de referencia.");

        expect($course->complexity_criteria)->toBe(
            ['c1' => $expected['c1'], 'c2' => $expected['c2'], 'c3' => $expected['c3'], 'c4' => $expected['c4']],
            "Los criterios de «{$course->name}» no coinciden con calc.py.",
        );

        expect($course->complexity_index)->toBe((float) $expected['prom'], "El índice de «{$course->name}» no coincide.");
        expect($course->complexity_level)->toBe($expected['nivel'], "El nivel de «{$course->name}» no coincide.");

        $compared++;
    }

    expect($compared)->toBe(61); // 64 menos los tres sin duración publicada
});

it('reproduce la distribución de niveles del Diccionario §3', function () {
    $this->seed([CatalogSeeder::class, CatalogAnalysisSeeder::class]);
    // 10 · 29 · 22 · 3 en el Diccionario, sobre 64 cursos. Aquí son 61: los tres
    // micro-cursos sin horas quedan sin nivel, y su N1 en calc.py era artefacto
    // del marcador 1 lección / 1 hora que la corrida usó para no dividir entre cero.
    $distribution = Course::whereNotNull('complexity_level')
        ->selectRaw('complexity_level, count(*) as total')
        ->groupBy('complexity_level')
        ->pluck('total', 'complexity_level')
        ->sortKeys()
        ->all();

    expect($distribution)->toBe([1 => 7, 2 => 29, 3 => 22, 4 => 3]);
});

it('asigna nivel a los 64 cursos con duración publicada en noviembre', function () {
    expect(Course::whereNull('complexity_level')->count())->toBe(0);
});

it('siembra las 64 descripciones cortas literales del sitio', function () {
    expect(Course::whereNotNull('short_description')->count())->toBe(64);
    expect(Course::where('slug', 'lean-manufacturing')->sole()->short_description)
        ->toBe('Elimina desperdicios con el sistema de producción Toyota');
});

it('permite recomendar todos los cursos con horas publicadas en noviembre', function () {
    expect(Course::recommendable()->count())->toBe(64);
    expect(Course::whereNotNull('allowed_layers')->count())->toBe(0);
});

it('mantiene el clúster de empleabilidad dentro del itinerario', function () {
    // docs/ARQUITECTURA.md §4 lo llama el puente de persuasión más fuerte del catálogo y le
    // da peso alto en la afinidad por arquetipo. El Diccionario lo marcaba
    // `no_ruta`; la decisión de negocio fue conservarlo recomendable.
    $cluster = Course::whereHas('tags', fn ($q) => $q->where('type', 'cluster')->where('slug', 'empleabilidad'))->get();

    expect($cluster)->toHaveCount(4);

    $cluster->each(function (Course $course) {
        expect($course->allowsLayer(RouteLayer::Itinerario))->toBeTrue("«{$course->name}» quedó fuera del itinerario.");
        expect($course->complexity_level)->not->toBeNull();
    });
});

it('marca los traslapes sospechados sin excluirlos automáticamente', function () {
    expect(Course::where('needs_overlap_review', true)->pluck('slug')->sort()->values()->all())
        ->toBe(['dmaic', 'kaizen-mejora-continua', 'lean-manufacturing']);

    // La sospecha no bloquea: los tres siguen siendo recomendables mientras el
    // sitio no declare la contención. Se marcó, no se inventó.
    expect(Course::where('needs_overlap_review', true)->whereNotNull('allowed_layers')->count())->toBe(0);
});

it('marca las duraciones que las fuentes contradicen', function () {
    expect(Course::where('needs_duration_review', true)->pluck('slug')->sort()->values()->all())
        ->toBe(['gestion-de-inventarios', 'kaizen-mejora-continua']);
});

it('devuelve null cuando faltan horas o lecciones, en vez de suponerlas', function () {
    $rubric = app(ComplexityRubric::class);

    expect($rubric->score(null, 8, 2, 3))->toBeNull();
    expect($rubric->score(16, null, 2, 3))->toBeNull();
    expect($rubric->score(16, 0, 2, 3))->toBeNull();
});

it('separa Black Belt de AutoCAD Completo por densidad, no por lecciones', function () {
    // La razón de ser del criterio C2, según el Diccionario §3: sin él, Black Belt
    // (10 lecciones) parecería más ligero que AutoCAD Completo (18).
    $blackBelt = Course::where('slug', 'black-belt-six-sigma')->sole();
    $autocad = Course::where('slug', 'autocad-completo')->sole();

    expect($blackBelt->lessons_count)->toBeLessThan($autocad->lessons_count);
    expect($blackBelt->complexity_criteria['c2'])->toBeGreaterThan($autocad->complexity_criteria['c2']);
    expect($blackBelt->complexity_level)->toBeGreaterThan($autocad->complexity_level);
});
