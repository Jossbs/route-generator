<?php

declare(strict_types=1);

use App\Models\BannedPhrase;
use App\Models\Course;
use App\Models\DocumentType;
use App\Models\Lead;
use App\Models\LearningRoute;
use App\Models\Specialization;
use App\Models\User;
use App\Services\Documents\DocumentView;
use App\Services\Documents\EditableCopy;
use App\Services\RouteComposition\ComposerRegistry;
use App\Services\RouteComposition\Support\PresetDraftBuilder;
use App\Services\RouteGeneration\Data\RouteInput;
use App\Services\RouteGeneration\PayloadBuilder;
use App\Services\RouteValidation\Data\RouteDraft;
use App\Services\RouteValidation\Data\ValidationReport;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Support\Str;
use Tests\Support\CompositionFactory;

/*
 * Cómo cae el documento en el papel.
 *
 * El PDF es el producto, y un documento que se desborda solo —hojas sin
 * encabezado ni folio, una especialización partida a la mitad— no lo parece. Las
 * hojas se cortan en `DocumentView`, donde se puede probar, y no en el navegador,
 * donde solo se puede mirar.
 *
 * Aquí no se miden píxeles: se comprueba lo que el corte tiene que garantizar.
 */

/** Renderiza el documento de una ruta recién compuesta, sin persistir nada. */
function documentoDe(string $slugDocumento, string $anclaSlug, ?RouteDraft $draft = null, ?Lead $lead = null): string
{
    $request = $slugDocumento === DocumentTypeSeeder::ANUAL
        ? CompositionFactory::anual($anclaSlug, lead: $lead)
        : CompositionFactory::enfocada($anclaSlug, lead: $lead);

    $draft ??= app(ComposerRegistry::class)->for($request->documentType)->compose($request);

    $payload = app(PayloadBuilder::class)->build(
        new RouteInput(
            lead: $request->lead,
            documentType: $request->documentType,
            anchorSpecialization: $request->anchorSpecialization,
            archetype: $request->archetype,
            membership: $request->membership,
        ),
        $draft,
        new ValidationReport([]),
    );

    $route = new LearningRoute(['snapshot' => $payload]);
    $route->setRelation('documentType', $request->documentType);

    return app(DocumentView::class)->for($route)->render();
}

/** Las hojas del documento, como las escribe la plantilla. */
function hojasDe(string $html): array
{
    preg_match_all('#<section class="hoja[^"]*">(.*?)</section>#s', $html, $m);

    return $m[1];
}

it('no deja ninguna hoja interior sin encabezado ni folio', function (string $documento, string $ancla) {
    $hojas = hojasDe(documentoDe($documento, $ancla));

    expect($hojas)->not->toBeEmpty();

    // La portada es la única sin encabezado: lleva el logo a tamaño completo.
    $huerfanas = [];

    foreach (array_slice($hojas, 1) as $numero => $hoja) {
        if (! str_contains($hoja, 'class="enc"') || ! str_contains($hoja, 'class="pie"')) {
            $huerfanas[] = $numero + 2;
        }
    }

    expect($huerfanas)->toBe([], 'Hojas sin encabezado o sin folio: '.implode(', ', $huerfanas));
})->with([
    'gancho' => [DocumentTypeSeeder::ENFOCADA, 'sixsigma'],
    'anual' => [DocumentTypeSeeder::ANUAL, 'sixsigma'],
]);

it('reparte una ruta larga en varias hojas sin partir ninguna especialización', function () {
    // Una ruta cargada de golpe desde un embudo puede traer muchos más cursos que
    // los que propone el motor. Es el caso que antes desbordaba: Chromium partía la
    // hoja por su cuenta y la segunda salía sin encabezado ni folio.
    $documentType = DocumentType::where('slug', DocumentTypeSeeder::ENFOCADA)->sole();
    $ancla = Specialization::where('slug', 'calidad')->sole();

    $lead = Lead::create([
        'name' => 'Lead de ruta larga',
        'source' => 'formulario',
        'requested_specialization_id' => $ancla->id,
    ]);

    $draft = app(PresetDraftBuilder::class)->build(
        documentType: $documentType,
        courseIds: Course::where('is_active', true)->orderBy('specialization_id')->pluck('id')->all(),
        anchorSpecializationId: $ancla->id,
    );

    $html = documentoDe(DocumentTypeSeeder::ENFOCADA, 'calidad', $draft);
    $hojas = hojasDe($html);

    expect(count($hojas))->toBeGreaterThan(3, 'Con el catálogo entero el gancho tiene que ocupar varias hojas.');

    foreach (array_slice($hojas, 1) as $numero => $hoja) {
        expect(str_contains($hoja, 'class="enc"'))->toBeTrue('La hoja '.($numero + 2).' salió sin encabezado.');
    }

    preg_match_all('#<div class="bl-tit">\s*<h3>(.*?)</h3>#s', $html, $m);

    // Cada especialización sale bajo un solo título. Si la paginación partiera un
    // bloque, el título se repetiría en la hoja siguiente o la mitad de la lista
    // quedaría sin él, que es lo que prohíbe NO_HEADING_MERGE.
    expect($m[1])->toHaveCount(count(array_unique($m[1])));
});

it('separa las cuatro etapas de la ruta anual', function () {
    $html = documentoDe(DocumentTypeSeeder::ANUAL, 'calidad');

    preg_match_all('#<div class="rf-etapa">\s*<span class="n">Etapa (\d+)</span>\s*<b>(.*?)</b>#s', $html, $etapas);

    expect(array_values(array_unique($etapas[1])))->toBe(['1', '2', '3', '4']);
});

it('pinta el itinerario del anual una sola vez', function () {
    $html = documentoDe(DocumentTypeSeeder::ANUAL, 'calidad');

    expect($html)->toContain('<div class="rf-h2">Tu ruta, mes a mes</div>')
        ->toContain('Todo lo que tienes abierto')
        ->not->toContain('Tu plan en una página')
        ->not->toContain('class="mes-card"');

    preg_match_all('#<div class="rf-mes(?: |")#', $html, $meses);
    expect($meses[0])->toHaveCount(12);
});

it('cuelga cada nivel absorbido de su paquete en el inventario', function () {
    $html = documentoDe(DocumentTypeSeeder::ANUAL, 'industrial');

    $posicion = fn (string $curso): int|false => mb_strpos($html, $curso);

    // «AutoCAD Completo» contiene a sus tres niveles. En el papel salen con «↳» y
    // la palabra «incluido»: si cayeran antes del paquete, la flecha no apuntaría a
    // nada y la contención que hace cuadrar 64 cursos con 55 programas parecería un
    // error de captura.
    expect($posicion('AutoCAD Completo'))->toBeLessThan($posicion('AutoCAD Básico'));

    // Y entre ellos, el orden pedagógico: básico nunca después de intermedio.
    expect($posicion('AutoCAD Básico'))->toBeLessThan($posicion('AutoCAD Intermedio'));
    expect($posicion('AutoCAD Intermedio'))->toBeLessThan($posicion('AutoCAD Avanzado'));
});

it('no imprime un solo precio ni una sola promesa de equivalencia', function (string $documento, string $ancla) {
    // El escaneo va sobre el documento ya armado, que es donde el lead lo lee: el
    // copy de catálogo, los nombres de los cursos y el texto de la plantilla
    // juntos. Es la restricción 5 de docs/ARQUITECTURA.md comprobada donde importa.
    $texto = strip_tags(documentoDe($documento, $ancla));

    foreach (BannedPhrase::where('is_active', true)->get() as $frase) {
        expect(preg_match($frase->pattern, $texto, $coincidencia))->toBe(
            0,
            'El documento imprime «'.($coincidencia[0] ?? '')."»: {$frase->reason}",
        );
    }
})->with([
    'gancho' => [DocumentTypeSeeder::ENFOCADA, 'sixsigma'],
    'anual' => [DocumentTypeSeeder::ANUAL, 'sixsigma'],
]);

it('declara las dos cifras del catálogo con su contención', function () {
    $html = documentoDe(DocumentTypeSeeder::ENFOCADA, 'sixsigma');

    // 64 cursos · 55 programas. El lead que sume tiene que cuadrar, y la razón de
    // que las dos cifras no coincidan va dicha a un lado.
    expect($html)
        ->toContain('Sigue aprendiendo durante tus 12 meses')
        ->toContain('programas de contenido distintos');
});

/*
 * Lo que el documento le dice al lead sobre sí mismo.
 *
 * Son las correcciones que salieron de leer un PDF entregado: una numeración que
 * parecía desfasada, una nota que prometía más programas de los que la ruta
 * llevaba, horas sin nada con qué aterrizarlas y un cierre sin a dónde tocar.
 */

/** Un lead con todo lo que el documento puede personalizar. */
function leadCapturado(string $anclaSlug, ?string $whatsapp = null): Lead
{
    $asesor = User::create([
        'name' => 'Rosa Beltrán',
        'email' => 'asesor-'.Str::random(6).'@dpsys.com.mx',
        'whatsapp' => $whatsapp,
    ]);

    return Lead::create([
        'name' => 'Salma Guerrero',
        'phone' => '8990000000',
        'source' => 'formulario',
        'occupation' => 'Analista de Calidad',
        'advisor_id' => $asesor->id,
        'requested_specialization_id' => Specialization::where('slug', $anclaSlug)->sole()->id,
    ]);
}

it('numera la portada y dice de cuántas hojas es el documento', function (string $documento, string $ancla) {
    $html = documentoDe($documento, $ancla);
    $hojas = count(hojasDe($html));
    $total = str_pad((string) $hojas, 2, '0', STR_PAD_LEFT);

    if ($documento === DocumentTypeSeeder::ANUAL) {
        expect($html)->toContain('<span>01 / '.$total.'</span>');

        preg_match_all('#<div class="pie">.*?<span>(\d{2}) / (\d{2})(?: ·|</span>)#s', $html, $folios);

        expect($folios[1])->toHaveCount($hojas - 1, 'Cada hoja interior lleva su folio.');
        expect(array_unique($folios[2]))->toBe([$total]);
        expect($folios[1])->toBe(array_map(
            fn (int $n): string => str_pad((string) $n, 2, '0', STR_PAD_LEFT),
            range(2, $hojas),
        ));

        return;
    }

    expect($html)->toContain('<span class="folio">01<i>/'.$total.'</i>');

    preg_match_all('#<span class="num">(\d+)<i>/(\d+)</i></span>#', $html, $folios);

    expect($folios[1])->toHaveCount($hojas - 1, 'Cada hoja interior lleva su folio.');
    expect(array_unique($folios[2]))->toBe([$total]);
    expect($folios[1])->toBe(array_map(
        fn (int $n): string => str_pad((string) $n, 2, '0', STR_PAD_LEFT),
        range(2, $hojas),
    ));
})->with([
    'gancho' => [DocumentTypeSeeder::ENFOCADA, 'sixsigma'],
    'anual' => [DocumentTypeSeeder::ANUAL, 'sixsigma'],
]);

it('escribe todas las duraciones en un solo formato', function (string $documento, string $ancla) {
    $html = documentoDe($documento, $ancla);

    if ($documento === DocumentTypeSeeder::ANUAL) {
        preg_match_all('#<span class="(?:h|x)">((?:\d+|—) h)</span>#', $html, $duraciones);

        expect($duraciones[1])->not->toBeEmpty('El documento no imprimió ninguna duración.');
        expect($html)->not->toContain('bl-meta');

        return;
    }

    // Antes cada sitio la escribía a su manera: versalitas con interletraje en el
    // encabezado de la especialización, minúsculas sueltas en la fila del curso.
    // En el PDF se leían como tres formatos distintos del mismo dato.
    preg_match_all('#<span class="[^"]*\bdur\b[^"]*">(.*?)</span>#', $html, $duraciones);

    expect($duraciones[1])->not->toBeEmpty('El documento no imprimió ninguna duración.');

    foreach ($duraciones[1] as $texto) {
        $plano = str_replace('&nbsp;', ' ', $texto);

        expect($plano)->toMatch('/^(\d+ programas? · )?(\d+|—) h$/');
    }

    expect($html)->not->toContain('bl-meta');
})->with([
    'gancho' => [DocumentTypeSeeder::ENFOCADA, 'sixsigma'],
    'anual' => [DocumentTypeSeeder::ANUAL, 'sixsigma'],
]);

it('conserva los cursos Core Tools sin imprimir la nota retirada', function () {
    $documentType = DocumentType::where('slug', DocumentTypeSeeder::ENFOCADA)->sole();
    $ancla = Specialization::where('slug', 'calidad')->sole();
    $coreTools = Specialization::where('slug', 'coretools')->sole();

    // Dos de los cuatro programas, que es el caso que antes mentía: el pie decía
    // que la ruta cubría AMEF, PPAP, SPC y Plan de Control aunque el desglose de la
    // hoja anterior solo enlistara dos.
    $draft = app(PresetDraftBuilder::class)->build(
        documentType: $documentType,
        courseIds: Course::whereIn('slug', ['amef', 'spc', 'iso-9001-2015-auditor-interno'])->pluck('id')->all(),
        anchorSpecializationId: $ancla->id,
    );

    $texto = strip_tags(documentoDe(DocumentTypeSeeder::ENFOCADA, 'calidad', $draft));

    expect($texto)
        ->toContain('AMEF')
        ->not->toContain('Core Tools en DP SyS son cuatro programas');

    // Y en ningún caso promete la especialización completa (CORE_TOOLS_PARTIAL).
    expect($texto)->not->toContain('No abarca APQP ni MSA');
    expect($coreTools->isPartial())->toBeTrue();
});

it('aterriza las horas en un tiempo, con el ritmo dicho al lado', function () {
    $html = documentoDe(DocumentTypeSeeder::ENFOCADA, 'sixsigma');

    // «96 horas» no le dice al lead si eso es un mes o un año. La estimación va
    // siempre con el ritmo con el que se calculó, para que se pueda comprobar, y
    // una sola vez: al pie del itinerario que describe.
    expect($html)->toContain('<p class="ritmo">A 18 horas por semana, la terminas en');
    expect(substr_count($html, '18 horas por semana'))->toBe(1);
});

it('presenta el objetivo sin depender del puesto o grado capturado', function () {
    $html = documentoDe(DocumentTypeSeeder::ENFOCADA, 'sixsigma', lead: leadCapturado('sixsigma'));

    // Solo baja la inicial: lo demás se queda como el asesor lo escribió.
    expect($html)->toContain('Empieza por Six Sigma')->not->toContain('Para tu puesto de');
});

it('no rompe las siglas de la ocupación al ponerla a media frase', function (string $capturada, string $impresa) {
    // Antes la ocupación se pasaba entera a minúsculas: «Gerente de TI» salía
    // «gerente de ti» en el documento del lead.
    expect(EditableCopy::inSentence($capturada))->toBe($impresa);
})->with([
    ['Gerente de TI', 'gerente de TI'],
    ['SQE de proveedores', 'SQE de proveedores'],
    ['Ing. de Calidad', 'ing. de Calidad'],
    ['Técnico de mantenimiento', 'técnico de mantenimiento'],
]);

it('presenta el objetivo de la ruta aunque no haya ocupación', function () {
    // Un marcador sin valor deja el bloque fuera: imprimir «tu rol de {ocupacion}»
    // delante de un lead es peor que no imprimir la frase.
    $html = documentoDe(DocumentTypeSeeder::ENFOCADA, 'sixsigma');

    expect($html)
        ->not->toContain('{ocupacion}')
        ->toContain('Empieza por Six Sigma');
});

it('enlaza al WhatsApp del asesor y no imprime un solo precio al hacerlo', function () {
    $html = documentoDe(
        DocumentTypeSeeder::ENFOCADA,
        'sixsigma',
        lead: leadCapturado('sixsigma', '899 123 4567'),
    );

    // Diez dígitos es un número nacional escrito como se escribe en México.
    expect($html)
        ->toContain('https://wa.me/528991234567?text=')
        ->toContain('Quiero activar mi membresía');

    // El botón de compra abre la conversación; el precio lo dice el asesor ahí,
    // donde puede atender objeciones (docs/ARQUITECTURA.md §4.5).
    foreach (BannedPhrase::where('is_active', true)->get() as $frase) {
        expect(preg_match($frase->pattern, strip_tags($html)))->toBe(0, $frase->reason);
    }
});

it('no pinta el botón cuando el perfil del asesor todavía no trae número', function () {
    $html = documentoDe(DocumentTypeSeeder::ENFOCADA, 'sixsigma', lead: leadCapturado('sixsigma'));

    // El perfil del asesor lo administra el sistema anfitrión: el dato puede llegar
    // tarde. Sin él el documento se lee igual, nunca con un enlace roto.
    expect($html)
        ->not->toContain('wa.me')
        ->not->toContain('<div class="cta-botones">');
});
