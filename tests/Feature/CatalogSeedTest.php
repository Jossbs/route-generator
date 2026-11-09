<?php

declare(strict_types=1);

use App\Models\BannedPhrase;
use App\Models\CatalogRule;
use App\Models\ContentUnit;
use App\Models\CopyBlock;
use App\Models\Course;
use App\Models\DocumentType;
use App\Models\Specialization;
use App\Services\Documents\InitialRouteScriptCopy;
use App\Services\RouteValidation\RuleInterpreterRegistry;
use Database\Seeders\DatabaseSeeder;

/*
 * El catálogo sembrado contra el sitio real (academy.dpsys.com.mx, 2026-09-08).
 * Si estas cifras se mueven, el documento empieza a mentirle al lead.
 */

it('siembra las 12 especializaciones y los 64 cursos publicados', function () {
    expect(Specialization::count())->toBe(12);
    expect(Course::count())->toBe(64);
});

it('siembra 55 programas de contenido distintos', function () {
    // 64 − 9 niveles absorbidos por Excel, AutoCAD y SolidWorks Completo.
    expect(ContentUnit::count())->toBe(55);
});

it('agrupa los cuatro cursos de cada familia bajo una sola unidad de contenido', function (string $unitSlug) {
    expect(ContentUnit::where('slug', $unitSlug)->sole()->courses()->count())->toBe(4);
})->with(['autocad', 'solidworks', 'microsoft-excel']);

it('marca Core Tools como parcial y nombra lo que le falta', function () {
    $coreTools = Specialization::where('slug', 'coretools')->sole();

    expect($coreTools->isPartial())->toBeTrue();
    expect($coreTools->missing_components)->toBe(['APQP', 'MSA']);
    expect($coreTools->courses()->count())->toBe(4);
});

it('deja Cadena de Suministros fuera de los roles de ancla y complementaria', function () {
    $suministros = Specialization::where('slug', 'suministros')->sole();

    expect($suministros->allowsRole('ancla'))->toBeFalse();
    expect($suministros->allowsRole('complementaria'))->toBeFalse();
    expect($suministros->allowsRole('bloque_itinerario'))->toBeTrue();
    expect($suministros->allowsRole('inventario'))->toBeTrue();
});

it('registra la contención de AutoCAD como parcial, con su nota de auditoría', function () {
    $completo = Course::where('slug', 'autocad-completo')->sole();
    $niveles = $completo->contains;

    expect($niveles)->toHaveCount(3);
    expect($niveles->first()->pivot->containment_type)->toBe('parcial');
    expect($niveles->sum('duration_hours'))->toBe(48)
        ->and($completo->duration_hours)->toBe(36);
});

it('siembra las frases de resultado en borrador, con texto literal del sitio', function () {
    // docs/ARQUITECTURA.md §6: se siembran en borrador con la descripción que ya publica cada
    // curso, y Contenido las pasa a aprobado. Que ninguna difiera de
    // `short_description` es lo que demuestra que ninguna se inventó.
    expect(Course::whereColumn('outcome_phrase', '!=', 'short_description')->count())->toBe(0);
    expect(Course::whereNull('outcome_phrase')->count())->toBe(0);
    expect(Course::where('outcome_phrase_status', 'aprobado')->count())->toBe(0);
});

it('deja en null las horas que el sitio no publica', function () {
    expect(Course::whereNull('duration_hours')->pluck('slug')->sort()->values()->all())
        ->toBe(['estrategias-de-negociacion', 'liderazgo-efectivo', 'mente-millonaria']);
});

it('etiqueta el clúster de empleabilidad con sus cuatro cursos', function () {
    $courses = Course::whereHas('tags', fn ($q) => $q->where('type', 'cluster')->where('slug', 'empleabilidad'))
        ->pluck('slug')->sort()->values()->all();

    expect($courses)->toBe([
        'como-hablar-en-una-entrevista-de-trabajo',
        'como-venderte-a-las-empresas',
        'curriculum-vitae-de-alto-impacto',
        'pqt-contraten',
    ]);
});

it('siembra las ocho restricciones de dominio, activas y bloqueantes', function () {
    // Se afirma que estas ocho están y bloquean, no cuántas reglas hay en total:
    // el catálogo de reglas crece —el Diccionario aporta unas, el módulo de
    // embudos otras— y un total fijo convierte cada regla nueva en una prueba rota
    // sin decir nada sobre lo que de verdad importa, que es que estas ocho vigilan.
    $rules = CatalogRule::query()->inEffect()->get()->keyBy('code');

    $obligatorias = [
        'SUPPLY_CHAIN_ROLE',
        'MODULE_DOUBLE_COUNT',
        'LEADERSHIP_OVERLAP',
        'CORE_TOOLS_PARTIAL',
        'NO_CERT_EQUIVALENCE',
        'ANNUAL_REQUIRES_MEMBERSHIP',
        'LEVEL_SEQUENCE',
        'NO_HEADING_MERGE',
    ];

    foreach ($obligatorias as $code) {
        expect($rules->has($code))->toBeTrue("La restricción [{$code}] de docs/ARQUITECTURA.md §4 no está sembrada.");
        expect($rules[$code]->isBlocking())->toBeTrue("La restricción [{$code}] dejó de bloquear.");
    }
});

it('siembra solo tipos de regla que tienen intérprete', function () {
    $registered = app(RuleInterpreterRegistry::class)->types();

    expect(CatalogRule::pluck('type')->unique()->diff($registered)->all())->toBe([]);
});

it('ancla cada regla con miembros a filas reales del catálogo', function () {
    expect(CatalogRule::where('code', 'LEADERSHIP_OVERLAP')->sole()->courses()->count())->toBe(3);
    expect(CatalogRule::where('code', 'SUPPLY_CHAIN_ROLE')->sole()->specializations()->count())->toBe(1);
    expect(CatalogRule::where('code', 'CORE_TOOLS_PARTIAL')->sole()->specializations()->count())->toBe(1);
});

it('siembra las frases prohibidas con expresiones regulares válidas', function () {
    BannedPhrase::each(function (BannedPhrase $phrase) {
        expect(@preg_match($phrase->pattern, 'texto de prueba'))
            ->not->toBeFalse("El patrón [{$phrase->pattern}] no compila.");
    });

    expect(BannedPhrase::active()->count())->toBeGreaterThan(0);
});

it('siembra los dos tipos de documento con su forma de entrega', function () {
    $enfocada = DocumentType::where('slug', 'ruta-enfocada')->sole();
    $anual = DocumentType::where('slug', 'ruta-anual')->sole();

    expect($enfocada->delivery)->toBe('enlace_rastreado');
    expect($enfocada->requires_tracking)->toBeTrue();
    expect($enfocada->requires_membership)->toBeFalse();
    expect($enfocada->layers)->toBe(['itinerario']);

    expect($anual->delivery)->toBe('archivo_descargable');
    expect($anual->requires_tracking)->toBeFalse();
    expect($anual->requires_membership)->toBeTrue();
    expect($anual->layers)->toBe(['itinerario', 'inventario']);
});

it('reserva el estado aprobado para los textos autorizados del complemento inicial', function () {
    // El guion y el mensaje fueron proporcionados por el usuario. El resto del
    // copy conserva su estado de borrador hasta recibir aprobación de Contenido.
    expect(CopyBlock::where('status', 'aprobado')->orderBy('slug')->pluck('slug')->all())
        ->toBe(collect(InitialRouteScriptCopy::slots())->pluck('slug')->sort()->values()->all());

    expect(CopyBlock::where('status', 'borrador')->whereNull('body')->count())->toBe(
        0,
        'Un bloque en borrador sin texto es un hueco disfrazado: va en pendiente.',
    );
});

it('sostiene cada bloque de copy con los hechos que afirma', function () {
    // `claims` es lo que hace revisable el copy. Sin él, Contenido tendría que
    // deducir de dónde salió cada afirmación, y docs/ARQUITECTURA.md §6 prohíbe justamente el
    // texto plausible sin fuente.
    $sinRespaldo = CopyBlock::whereNotNull('body')
        ->get()
        ->filter(fn (CopyBlock $bloque): bool => blank($bloque->claims))
        ->pluck('slug')
        ->all();

    expect($sinRespaldo)->toBe([], 'Bloques con texto y sin claims: '.implode(', ', $sinRespaldo));
});

it('no siembra copy que el propio validador rechazaría', function () {
    $texto = CopyBlock::whereNotNull('body')->pluck('body')->implode("\n");

    foreach (BannedPhrase::active()->get() as $frase) {
        expect(preg_match($frase->pattern, $texto, $coincidencia))->toBe(
            0,
            'El copy sembrado dice «'.($coincidencia[0] ?? '')."»: {$frase->reason}",
        );
    }
});

it('vuelve a correr sin duplicar nada', function () {
    $antes = CatalogRule::count();

    $this->seed(DatabaseSeeder::class);

    expect(Course::count())->toBe(64);
    expect(ContentUnit::count())->toBe(55);
    expect(DB::table('course_containments')->count())->toBe(9);
    // El total de reglas se compara contra sí mismo: lo que se prueba es que
    // sembrar dos veces reconcilia en vez de duplicar, no cuántas reglas hay.
    expect(CatalogRule::count())->toBe($antes);
});
