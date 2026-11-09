<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\CopyBlock;
use App\Models\LearningRoute;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Arma el documento desde el snapshot.
 *
 * La agrupación vive aquí y no en la plantilla porque es donde se cumplen dos
 * restricciones: cada especialización sale bajo su propio título y nunca revuelta
 * con otra (`NO_HEADING_MERGE`), y los niveles que un paquete «Completo» absorbe
 * se listan colgando de él en vez de repetir sus horas (`MODULE_DOUBLE_COUNT`).
 * Una plantilla que agrupara por su cuenta podría dejar de cumplirlas sin que
 * nadie se enterara.
 *
 * El catálogo no se consulta. Todo sale del payload congelado.
 */
final class DocumentView
{
    private const MESES = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    private const MESES_CORTOS = [
        1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr',
        5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago',
        9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
    ];

    /**
     * El presupuesto de alto de una hoja, en píxeles de cuerpo.
     *
     * El itinerario anual no cabía en una hoja y Chromium lo desbordaba solo: las
     * hojas que inventaba salían sin encabezado y sin folio, que es exactamente lo
     * que un documento de venta no se puede permitir. Ahora el corte se decide
     * aquí, y cada hoja que sigue lleva su encabezado con «(2)», «(3)».
     *
     * Las medidas son las del render real en Carta con la raíz en 12 px, no
     * estimaciones: la hoja son 279 mm menos sus márgenes, menos el encabezado y
     * el pie. La primera hoja de una sección descuenta además el título y la
     * entradilla. Si se toca el ritmo vertical de `_estilos`, estas cuatro
     * constantes se vuelven a medir.
     */
    private const ALTO_HOJA = 850.0;

    /**
     * La primera hoja del itinerario pierde además el título de sección y el
     * bloque de objetivo, que es más alto que la entradilla genérica a la que
     * sustituye: lleva su propio marco y su marbete. Remedido el 2026-09-24,
     * cuando el objetivo quedó en un renglón.
     */
    private const ALTO_PRIMERA = 760.0;

    private const ALTO_ENCABEZADO_BLOQUE = 53.0;

    private const ALTO_CURSO = 43.0;

    /**
     * Un curso sin frase de resultado ya no colapsa a un renglón apretado: la fila
     * tiene alto mínimo para que dos seguidos se lean como dos pasos de la ruta y
     * no como una nota al pie contra el margen.
     */
    private const ALTO_CURSO_SIN_FRASE = 34.0;

    /**
     * El cronograma del anual: un renglón por curso, de alto fijo, con el nombre
     * recortado en una línea. Con el alto fijo el corte de hojas es aritmética y
     * no una estimación de cuántos nombres envuelven.
     *
     * La primera hoja pierde el título de sección y las cuatro cifras del plan;
     * todas pierden la cabecera de meses, que se repite para que cada hoja se lea
     * sola. La última pierde además la fila de horas por mes y la nota. Medido
     * sobre el render en Carta el 2026-09-24.
     */
    private const ALTO_PRIMERA_CRONOGRAMA = 685.0;

    private const ALTO_CRONOGRAMA = 820.0;

    /** La fila de horas por mes y la nota, que solo lleva la última hoja. */
    private const ALTO_CRONO_CIERRE = 64.0;

    /** La marca «Empieza aquí» sobre el primer curso del gancho. */
    private const ALTO_EMPIEZA = 16.0;

    private const ALTO_CRONO_GRUPO = 27.0;

    private const ALTO_CRONO_FILA = 21.0;

    /** La frase de ritmo que cierra el itinerario del gancho. */
    private const ALTO_RITMO = 30.0;

    /** Título de grupo, con sus márgenes y separación de la primera especialidad. */
    private const ALTO_GRUPO_PRINCIPAL = 40.0;

    /** Incluye el contexto del lead, limitado a 240 caracteres en la plantilla. */
    private const ALTO_GRUPO_REFUERZOS = 110.0;

    /** Separación entre bloques de la lista flexible de la ruta inicial. */
    private const ESPACIO_BLOQUE = 10.0;

    /** Cuántas reseñas lleva la hoja «Quiénes somos». */
    private const RESENAS = 2;

    /**
     * Cuántos programas publica Core Tools: AMEF, PPAP, SPC y Plan de Control.
     *
     * Le faltan APQP y MSA, dos de las cinco herramientas AIAG, y por eso ningún
     * texto puede prometerla completa (docs/ARQUITECTURA.md §4.4). Aquí la cifra solo sirve
     * para saber si la ruta se llevó todos o dejó alguno fuera; el hecho en sí lo
     * dice el bloque de copy `nota.core-tools`, que es donde el validador lo ve.
     */
    private const CORE_TOOLS_PROGRAMAS = 4;

    public function __construct(
        private readonly CopyResolver $copy,
        private readonly WhatsAppLink $whatsapp,
        private readonly EditableCopy $editable,
        private readonly InventoryLayout $inventario,
    ) {}

    public function for(LearningRoute $route, bool $imprimible = false): View
    {
        $doc = $route->snapshot ?? [];
        $anual = $route->documentType->slug === DocumentTypeSeeder::ANUAL;

        $itinerario = $this->rows($doc, 'itinerario');

        $agrupados = $this->bySpecialization($itinerario);

        if (! $anual) {
            $reasons = new RecommendationReason;
            $hasContext = filled($doc['lead']['contexto'] ?? null);
            $itinerario = array_map(fn (array $course): array => [
                ...$course,
                'motivo_recomendacion' => ($course['rol'] ?? null) === 'complementaria'
                    ? $reasons->for($course)
                    : null,
            ], $itinerario);
            $principales = array_filter($itinerario, fn (array $c): bool => ($c['rol'] ?? null) !== 'complementaria');
            $adicionales = array_filter($itinerario, fn (array $c): bool => ($c['rol'] ?? null) === 'complementaria');
            $agrupados = [...$this->bySpecialization(array_values($principales)), ...$this->bySpecialization(array_values($adicionales))];
        }

        $datos = [
            'doc' => $doc,
            'copy' => $this->copy,
            'imprimible' => $imprimible,
            'titulo' => $this->title($doc, $anual),
            'fecha' => $this->date($doc),
            'fechaCorta' => $this->shortDate($doc),
            'iniciales' => $this->initials($doc['asesor']['nombre'] ?? ''),
            'agrupados' => $agrupados,
            'coreTools' => $this->coreTools($itinerario),
            'porque' => $this->why($doc, $itinerario),
            'catalogo' => $this->catalog($doc),
            'objetivo' => $this->objective($doc, $anual),
            'bajada' => $this->adjusted($doc, $anual ? 'portada.ruta-anual' : 'portada.ruta-enfocada')
                ?? $this->editable->default($anual ? 'portada.ruta-anual' : 'portada.ruta-enfocada', []),
            'primerCurso' => $itinerario[0]['nombre'] ?? null,
            'vigencia' => $this->term($doc),
            'vigenciaCorta' => $this->shortTerm($doc),
            'ritmo' => $this->pace($doc),
            'horasSemana' => RouteDuration::weeklyHours($doc),
            'enlaces' => $this->links($doc),
        ];

        if (! $anual) {
            // El gancho cabe en una hoja salvo que el asesor haya cargado la
            // ruta; si no cabe, sigue en otra con su encabezado, nunca en una
            // hoja huérfana.
            $hojasRuta = $this->paginate(
                $agrupados,
                self::ALTO_PRIMERA - self::ALTO_RITMO,
                self::ALTO_HOJA - self::ALTO_RITMO,
            );

            return view('documentos.enfocada', [
                ...$datos,
                // El nombre del embudo es lo que el lead vio en el anuncio que lo
                // trajo; la especialidad ancla era lenguaje del catálogo. Sin
                // embudo, la ancla (decidido el 2026-09-24).
                'tituloPortada' => $this->coverTitle($doc),
                'hojasRuta' => $hojasRuta,
                'confianza' => $this->socialProof($itinerario),
                'contextoRefuerzos' => $hasContext
                    ? Str::limit(trim((string) $doc['lead']['contexto']), 240)
                    : null,
                // Portada + itinerario + quiénes somos + siguiente paso. El folio
                // lo necesita para decir «02 / 04»: sin el total, la portada sin
                // número se leía como una página perdida.
                'paginas' => count($hojasRuta) + 3,
            ]);
        }

        // El anual conserva la secuencia en doce nodos mensuales. Un curso que
        // cruza de un mes al siguiente reaparece como «continúa», sin partir la
        // tarjeta ni perder la carga de cada mes.
        $meses = $this->byMonth($itinerario, $doc);
        $mesesRuta = $this->annualMonths($meses, $doc);
        $hojasMeses = $this->paginateMonths($this->stages($mesesRuta));
        $hojasCronograma = $this->paginateSchedule($this->schedule($agrupados));
        $hojasInventario = $this->inventario->paginate(
            $this->markPlanned(
                $this->inventario->nest($this->bySpecialization($this->rows($doc, 'inventario'))),
                $itinerario,
            ),
            columnas: 2,
        );

        return view('documentos.anual', [
            ...$datos,
            'totalesItinerario' => $this->itineraryTotals($itinerario),
            'columnasMeses' => $this->monthColumns($doc),
            'horasPorMes' => $this->hoursByMonth($meses),
            'mesesRuta' => $mesesRuta,
            'etapasRuta' => $this->stages($mesesRuta),
            'hojasMeses' => $hojasMeses,
            'hojasCronograma' => $hojasCronograma,
            'hojasInventario' => $hojasInventario,
            'paginas' => count($hojasInventario) + count($hojasMeses) + 2,
        ]);
    }

    /**
     * Cada hoja conserva meses completos. Si un mes acumula muchos cursos, sus
     * tarjetas continúan en otro bloque identificado; ninguna queda recortada.
     * El presupuesto incluye espacio para la meta y el pie de la última hoja.
     *
     * @param  list<array<string, mixed>>  $stages
     * @return list<list<array<string, mixed>>>
     */
    private function paginateMonths(array $stages): array
    {
        $pages = [];
        $page = [];
        $used = 0;
        $limit = 710;

        foreach ($stages as $stage) {
            foreach ($stage['meses'] as $month) {
                $chunks = array_chunk($month['cursos'], 9) ?: [[]];

                foreach ($chunks as $part => $courses) {
                    $closing = $month['numero'] === 12 && $part === count($chunks) - 1 ? 140 : 0;
                    $sameStage = $page !== [] && $page[array_key_last($page)]['numero'] === $stage['numero'];
                    $height = 52 + max(60, count($courses) * 48) + ($sameStage ? 0 : 54) + $closing;

                    if ($page !== [] && $used + $height > $limit) {
                        $pages[] = $page;
                        $page = [];
                        $used = 0;
                        $limit = 835;
                        $sameStage = false;
                        $height = 106 + max(60, count($courses) * 48) + $closing;
                    }

                    if (! $sameStage) {
                        $page[] = [...$stage, 'meses' => []];
                    }

                    $page[array_key_last($page)]['meses'][] = [
                        ...$month,
                        'cursos' => $courses,
                        'continuacion' => $part > 0,
                    ];
                    $used += $height;
                }
            }
        }

        if ($page !== []) {
            $pages[] = $page;
        }

        return $pages;
    }

    /**
     * La meta del año cuenta solo lo recomendado, no todo el inventario abierto.
     * Las horas se calculan desde el snapshot y cada paquete absorbe sus niveles
     * únicamente cuando también forma parte del itinerario.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{cursos: int, horas_netas: int}
     */
    private function itineraryTotals(array $rows): array
    {
        $seen = [];
        /** @var array<int|string, array{horas: int, paquete: ?int}> $units */
        $units = [];

        foreach ($rows as $row) {
            $slug = (string) $row['slug'];

            if (isset($seen[$slug])) {
                continue;
            }

            $seen[$slug] = true;
            $unit = $row['unidad'] ?? $slug;
            $hours = (int) ($row['horas'] ?? 0);
            $units[$unit] ??= ['horas' => 0, 'paquete' => null];
            $units[$unit]['horas'] += $hours;

            if ($row['es_paquete'] ?? false) {
                $units[$unit]['paquete'] = $hours;
            }
        }

        return [
            'cursos' => count($seen),
            'horas_netas' => array_sum(array_map(
                static fn (array $unit): int => $unit['paquete'] ?? $unit['horas'],
                $units,
            )),
        ];
    }

    /**
     * Los renglones de una capa. El gancho solo lleva itinerario; el anual lleva
     * las dos, y la diferencia importa: la exclusión de liderazgo se evalúa sobre
     * lo recomendado, no sobre lo incluido, así que el inventario puede listar los
     * tres cursos que el itinerario no puede recomendar juntos.
     *
     * @param  array<string, mixed>  $doc
     * @return list<array<string, mixed>>
     */
    private function rows(array $doc, string $layer): array
    {
        return array_values(array_filter(
            $doc['ruta']['cursos'] ?? [],
            fn (array $row): bool => ($row['capa'] ?? 'itinerario') === $layer,
        ));
    }

    /**
     * Un bloque por especialización, en el orden en que aparecen.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function bySpecialization(array $rows): array
    {
        $bloques = [];

        foreach ($rows as $row) {
            $clave = $row['especialidad'] ?? 'Sin especialidad';

            $bloques[$clave] ??= [
                'especialidad' => $clave,
                'rol' => $row['rol'] ?? null,
                'rol_etiqueta' => $this->roleLabel($row['rol'] ?? null),
                'cursos' => [],
                'programas' => 0,
                'horas' => 0,
            ];

            $bloques[$clave]['cursos'][] = $row;

            // Un nivel absorbido por un paquete presente no suma: sus horas ya
            // están dentro de las del paquete. Contarlo sería el doble conteo que
            // MODULE_DOUBLE_COUNT prohíbe.
            if (! ($row['horas_absorbidas'] ?? false)) {
                $bloques[$clave]['programas']++;
                $bloques[$clave]['horas'] += (int) ($row['horas'] ?? 0);
            }
        }

        return array_values($bloques);
    }

    /**
     * El recorrido por meses. Dentro de cada mes, las especializaciones siguen
     * separadas: dos pueden compartir un mes, jamás un encabezado.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $doc
     * @return list<array<string, mixed>>
     */
    private function byMonth(array $rows, array $doc): array
    {
        $meses = [];

        foreach ($rows as $row) {
            $numero = (int) ($row['mes_sugerido'] ?? 0);

            if ($numero < 1) {
                continue;
            }

            // Un curso más largo que un mes a su ritmo se imprime donde empieza,
            // con su rango, y los meses que cubre dicen «continúa». Sus horas se
            // reparten entre ellos: cargarlas todas al primero haría ver un mes
            // imposible seguido de otros casi vacíos.
            $fin = max($numero, (int) ($row['mes_fin'] ?? $numero));
            $tramo = $fin - $numero + 1;
            $horas = ($row['horas_absorbidas'] ?? false) ? 0 : (int) ($row['horas'] ?? 0);

            foreach (range($numero, $fin) as $mes) {
                $meses[$mes] ??= [
                    'numero' => $mes,
                    'nombre' => $this->monthName($mes, $doc),
                    'horas' => 0,
                    'bloques' => [],
                ];

                $meses[$mes]['bloques'][] = [
                    ...$row,
                    'continua' => $mes > $numero,
                    'meses' => $tramo > 1 ? $numero.'–'.$fin : null,
                ];

                // Reparto entero sin perder ni duplicar horas al cruzar meses.
                $meses[$mes]['horas'] += intdiv($horas, $tramo)
                    + ($mes - $numero < $horas % $tramo ? 1 : 0);
            }
        }

        ksort($meses);

        return array_values(array_map(
            fn (array $mes): array => [...$mes, 'bloques' => $this->bySpecialization($mes['bloques'])],
            $meses,
        ));
    }

    /**
     * El mes del calendario que le toca, contado desde el inicio de la vigencia.
     *
     * Es una recomendación de secuencia, nunca un compromiso: los cursos son
     * autogestivos con acceso 24/7, así que el documento dice «mes 1 sugerido» y
     * no una fecha.
     *
     * @param  array<string, mixed>  $doc
     */
    private function monthName(int $numero, array $doc): string
    {
        $inicio = $doc['membresia']['starts_on'] ?? null;

        if ($inicio === null) {
            return "Mes {$numero}";
        }

        $fecha = Carbon::parse($inicio)->addMonths($numero - 1);

        return Str::ucfirst(self::MESES[$fecha->month]);
    }

    /** @param array<string, mixed> $doc */
    private function title(array $doc, bool $anual): string
    {
        $nombre = $doc['lead']['nombre'] ?? 'Ruta formativa';

        return $anual
            ? "Tu año de formación · {$nombre}"
            : "Ruta principal personalizada · {$nombre}";
    }

    /** @param array<string, mixed> $doc */
    private function date(array $doc): string
    {
        return $this->longDate(Carbon::parse($doc['generado_en'] ?? now()));
    }

    /**
     * La vigencia de la membresía, escrita como se escribe.
     *
     * El snapshot la guarda en ISO porque ordena los doce meses, pero la portada la
     * imprimía tal cual: «2026-09-10» al lado de una fecha de emisión que sí decía
     * «17 de septiembre de 2026». Dos formatos de fecha en la misma hoja de un
     * documento de venta se leen como un descuido, que es lo último que puede
     * permitirse la cortesía que se manda al cerrar la membresía.
     *
     * @param  array<string, mixed>  $doc
     * @return array<string, string>|null
     */
    private function term(array $doc): ?array
    {
        $inicio = $doc['membresia']['starts_on'] ?? null;
        $fin = $doc['membresia']['ends_on'] ?? null;

        if (blank($inicio) || blank($fin)) {
            return null;
        }

        return [
            'inicio' => $this->longDate(Carbon::parse($inicio)),
            'fin' => $this->longDate(Carbon::parse($fin)),
        ];
    }

    private function longDate(Carbon $fecha): string
    {
        return $fecha->day.' de '.self::MESES[$fecha->month].' de '.$fecha->year;
    }

    /**
     * La fecha corta que usa el documento final.
     *
     * @param  array<string, mixed>  $doc
     */
    private function shortDate(array $doc): string
    {
        $fecha = Carbon::parse($doc['generado_en'] ?? now());

        return $fecha->day.' '.self::MESES_CORTOS[$fecha->month].' '.$fecha->year;
    }

    /**
     * @param  array<string, mixed>  $doc
     * @return array{inicio: string, fin: string}|null
     */
    private function shortTerm(array $doc): ?array
    {
        $inicio = $doc['membresia']['starts_on'] ?? null;
        $fin = $doc['membresia']['ends_on'] ?? null;

        if (blank($inicio) || blank($fin)) {
            return null;
        }

        $inicioFecha = Carbon::parse($inicio);
        $finFecha = Carbon::parse($fin);

        return [
            'inicio' => self::MESES_CORTOS[$inicioFecha->month].' '.$inicioFecha->year,
            'fin' => self::MESES_CORTOS[$finFecha->month].' '.$finFecha->year,
        ];
    }

    private function initials(string $nombre): string
    {
        $partes = preg_split('/\s+/', trim($nombre)) ?: [];

        return Str::upper(mb_substr($partes[0] ?? '', 0, 1).mb_substr($partes[1] ?? '', 0, 1));
    }

    /**
     * Qué programas de Core Tools lleva el itinerario, y cuántos quedaron fuera.
     *
     * Antes la nota decía que la ruta «cubre AMEF, PPAP, SPC y Plan de Control»
     * aunque solo llevara dos de los cuatro: enumeraba la especialización y no la
     * ruta, y un lead de cadena de suministro automotriz que revisara el desglose
     * encontraba dos programas donde el pie le prometía cuatro. Ahora la nota dice
     * lo que el lead tiene enfrente, y el hecho de que Core Tools sea parcial lo
     * pone el bloque de copy, que es donde el validador lo escanea.
     *
     * Se evalúa sobre el itinerario, nunca sobre el inventario: lo que se
     * recomienda es lo que la nota tiene que explicar.
     *
     * @param  list<array<string, mixed>>  $itinerario
     * @return array<string, mixed>|null
     */
    private function coreTools(array $itinerario): ?array
    {
        $nombres = [];

        foreach ($itinerario as $row) {
            if (Str::contains(Str::lower((string) ($row['especialidad'] ?? '')), 'core tools')) {
                $nombres[] = (string) $row['nombre'];
            }
        }

        if ($nombres === []) {
            return null;
        }

        return [
            'en_ruta' => $this->enumerate($nombres),
            'fuera' => max(0, self::CORE_TOOLS_PROGRAMAS - count($nombres)),
        ];
    }

    /**
     * La frase que vuelve la ruta suya y no una plantilla.
     *
     * Se arma con lo que el asesor capturó —la ocupación— y con la especialización
     * que el motor eligió. Si falta cualquiera de las dos, `CopyResolver` devuelve
     * null y el documento cae en su entradilla genérica: nada se inventa para
     * rellenar (docs/ARQUITECTURA.md §6).
     *
     * @param  array<string, mixed>  $doc
     */
    private function objective(array $doc, bool $anual): ?string
    {
        $slug = $anual ? 'objetivo.ruta-anual' : 'objetivo.ruta-enfocada';

        if (($ajustado = $this->adjusted($doc, $slug)) !== null) {
            return $ajustado;
        }

        $ocupacion = $anual
            ? ($doc['membresia']['occupation'] ?? $doc['lead']['ocupacion'] ?? null)
            : ($doc['lead']['ocupacion'] ?? null);

        return $this->editable->default($slug, [
            'ocupacion' => $ocupacion,
            'ancla' => $anual
                ? ($this->rows($doc, 'itinerario')[0]['especialidad'] ?? $doc['ruta']['especialidad_principal'] ?? null)
                : $this->coverTitle($doc),
        ]);
    }

    /**
     * El párrafo que el asesor ajustó a mano en la revisión, si lo ajustó.
     *
     * Viaja en el snapshot ya escaneado: el validador lo revisó junto con el copy
     * del catálogo antes de emitir (decidido el 2026-09-24). Solo aplica a los
     * tres párrafos editables; lo demás siempre sale del catálogo.
     *
     * @param  array<string, mixed>  $doc
     */
    private function adjusted(array $doc, string $slug): ?string
    {
        $texto = $doc['ajustes_copy'][$slug] ?? null;

        return is_string($texto) && trim($texto) !== '' ? trim($texto) : null;
    }

    /**
     * El título de la portada inicial: la ruta consultada. Los snapshots antiguos
     * recurren al nombre del embudo o a la especialización principal.
     *
     * @param  array<string, mixed>  $doc
     */
    private function coverTitle(array $doc): string
    {
        $nombreRuta = trim((string) ($doc['ruta']['nombre'] ?? ''));

        if ($nombreRuta !== '') {
            return $nombreRuta;
        }

        $embudo = trim((string) ($doc['embudo']['nombre_publico'] ?? ''));

        return $embudo !== '' ? $embudo : ($doc['ruta']['especialidad_principal'] ?? 'Tu ruta formativa');
    }

    /**
     * Cuánto le va a tomar, a un ritmo dicho.
     *
     * «96 horas» no es accionable: el lead no sabe si eso es un mes o un año. La
     * cifra sale de las horas netas de su propia ruta —las mismas que imprime el
     * panel— divididas entre un ritmo de referencia que va escrito al lado, para
     * que la estimación se pueda comprobar en vez de creerse.
     *
     * @param  array<string, mixed>  $doc
     */
    private function pace(array $doc): ?string
    {
        $duracion = RouteDuration::for($doc);

        return $duracion === null ? null : $this->copy->body('ritmo.estudio', [
            'horas_semana' => (string) RouteDuration::weeklyHours($doc),
            'duracion' => $duracion,
        ]);
    }

    /**
     * Los enlaces de vuelta al asesor.
     *
     * El documento no termina: devuelve la conversación a quien puede atender
     * objeciones. Son dos intenciones distintas y por eso son dos botones —resolver
     * una duda y activar la membresía— con su propio mensaje precargado, para que
     * al asesor le llegue ya dicho a qué viene el lead.
     *
     * Ninguno imprime precio: eso lo dice el asesor en la conversación
     * (docs/ARQUITECTURA.md §4.5). Sin número en el perfil del asesor no se pinta nada.
     *
     * @param  array<string, mixed>  $doc
     * @return array<string, string>|null
     */
    private function links(array $doc): ?array
    {
        $numero = $doc['asesor']['whatsapp'] ?? null;
        $nombre = $doc['lead']['nombre'] ?? null;

        if (blank($numero)) {
            return null;
        }

        $enlaces = array_filter([
            'conversacion' => $this->whatsapp->for(
                $numero,
                $this->copy->body('cta.mensaje-whatsapp', ['nombre' => $nombre]),
            ),
            'membresia' => $this->whatsapp->for(
                $numero,
                $this->copy->body('cta.mensaje-membresia', ['nombre' => $nombre]),
            ),
        ]);

        return $enlaces === [] ? null : $enlaces;
    }

    /**
     * Una lista en español: «A, B y C». El documento enumera cursos en prosa y
     * «A, B, C» delante de un lead se lee como una tabla mal pegada.
     *
     * @param  list<string>  $partes
     */
    private function enumerate(array $partes): string
    {
        if (count($partes) === 1) {
            return $partes[0];
        }

        $ultima = array_pop($partes);

        return implode(', ', $partes).' y '.$ultima;
    }

    /**
     * El cronograma: las especializaciones del itinerario, cada una bajo su
     * propio encabezado (NO_HEADING_MERGE), con sus cursos en el orden de la
     * secuencia, que ya respeta LEVEL_SEQUENCE. Cada curso dice en qué mes
     * empieza y en cuál termina; un curso largo es una barra larga.
     *
     * @param  list<array<string, mixed>>  $agrupados
     * @return list<array<string, mixed>>
     */
    private function schedule(array $agrupados): array
    {
        return array_map(fn (array $grupo): array => [
            'especialidad' => $grupo['especialidad'],
            'programas' => $grupo['programas'],
            'horas' => $grupo['horas'],
            'cursos' => array_values(array_map(function (array $curso): array {
                $inicio = max(1, (int) ($curso['mes_sugerido'] ?? 1));

                return [
                    ...$curso,
                    'inicio' => $inicio,
                    'fin' => max($inicio, (int) ($curso['mes_fin'] ?? $inicio)),
                ];
            }, $grupo['cursos'])),
        ], $agrupados);
    }

    /**
     * Reparte el cronograma en hojas sin partir ninguna especialización: partirla
     * dejaría media lista bajo un encabezado y la otra media sin él.
     *
     * @param  list<array<string, mixed>>  $grupos
     * @return list<list<array<string, mixed>>>
     */
    private function paginateSchedule(array $grupos): array
    {
        if ($grupos === []) {
            return [];
        }

        $hojas = [];
        $actual = [];
        $usado = 0.0;
        $tope = self::ALTO_PRIMERA_CRONOGRAMA;

        foreach ($grupos as $grupo) {
            $alto = self::ALTO_CRONO_GRUPO + count($grupo['cursos']) * self::ALTO_CRONO_FILA;

            if ($actual !== [] && $usado + $alto > $tope) {
                $hojas[] = $actual;
                $actual = [];
                $usado = 0.0;
                $tope = self::ALTO_CRONOGRAMA;
            }

            $actual[] = $grupo;
            $usado += $alto;
        }

        // La última hoja lleva además la fila de horas por mes y la nota. Si no
        // caben, su último grupo baja a una hoja propia con ellas.
        if (count($actual) > 1 && $usado + self::ALTO_CRONO_CIERRE > $tope) {
            $hojas[] = array_slice($actual, 0, -1);
            $actual = array_slice($actual, -1);
        }

        $hojas[] = $actual;

        return $hojas;
    }

    /**
     * Las doce columnas del cronograma, con el mes real que les toca desde el
     * inicio de la vigencia: «Oct», «Nov»… Sin vigencia, «M1», «M2»…
     *
     * @param  array<string, mixed>  $doc
     * @return list<string>
     */
    private function monthColumns(array $doc): array
    {
        $inicio = $doc['membresia']['starts_on'] ?? null;

        return array_map(function (int $numero) use ($inicio): string {
            if ($inicio === null) {
                return 'M'.$numero;
            }

            $mes = Carbon::parse($inicio)->addMonthsNoOverflow($numero - 1)->month;

            return Str::ucfirst(mb_substr(self::MESES[$mes], 0, 3));
        }, range(1, 12));
    }

    /**
     * Las horas de cada mes, que el miembro puede comparar contra su ritmo: es
     * lo que ANNUAL_FITS_PACE garantiza, dicho en el papel.
     *
     * @param  list<array<string, mixed>>  $meses
     * @return array<int, int>
     */
    private function hoursByMonth(array $meses): array
    {
        $horas = array_fill(1, 12, 0);

        foreach ($meses as $mes) {
            if (isset($horas[$mes['numero']])) {
                $horas[$mes['numero']] = (int) $mes['horas'];
            }
        }

        return $horas;
    }

    /**
     * Los doce meses normalizados para la vista cronológica del PDF final.
     *
     * `byMonth` solo devuelve meses con cursos porque también alimenta el cálculo
     * de carga. El documento, en cambio, tiene que mostrar los doce nodos aunque
     * alguno quede libre, y necesita los cursos aplanados dentro de cada nodo.
     *
     * @param  list<array<string, mixed>>  $meses
     * @param  array<string, mixed>  $doc
     * @return list<array<string, mixed>>
     */
    private function annualMonths(array $meses, array $doc): array
    {
        $porNumero = [];

        foreach ($meses as $mes) {
            $porNumero[(int) $mes['numero']] = $mes;
        }

        $resultado = [];

        foreach (range(1, 12) as $numero) {
            $mes = $porNumero[$numero] ?? [
                'numero' => $numero,
                'nombre' => $this->monthName($numero, $doc),
                'horas' => 0,
                'bloques' => [],
            ];

            $cursos = [];

            foreach ($mes['bloques'] as $bloque) {
                foreach ($bloque['cursos'] as $curso) {
                    $inicio = max(1, (int) ($curso['mes_sugerido'] ?? $numero));
                    $fin = max($inicio, (int) ($curso['mes_fin'] ?? $inicio));

                    $cursos[] = [
                        ...$curso,
                        'continua' => $numero > $inicio,
                        'parte' => $numero - $inicio + 1,
                        'partes' => $fin - $inicio + 1,
                    ];
                }
            }

            $horas = (int) ($mes['horas'] ?? 0);

            $resultado[] = [
                ...$mes,
                'cursos' => $cursos,
                'etiqueta' => $this->monthCalendarLabel($numero, $doc),
                'etiqueta_corta' => preg_replace('/(\d{2})(\d{2})$/', '$2', $this->monthCalendarLabel($numero, $doc)),
                // Cuatro semanas dan una carga aproximada entendible sin
                // prometer un calendario rígido para cursos autogestivos.
                'semanas' => $horas > 0 ? max(1, (int) floor($horas / 4)) : null,
            ];
        }

        return $resultado;
    }

    /**
     * Agrupa la ruta en cuatro etapas trimestrales, como la referencia visual.
     * Las especialidades siguen separadas dentro de cada curso; el nombre de la
     * etapa solo resume qué áreas aparecen en esos tres meses.
     *
     * @param  list<array<string, mixed>>  $meses
     * @return list<array<string, mixed>>
     */
    private function stages(array $meses): array
    {
        $etapas = [];

        foreach ([[1, 3], [4, 6], [7, 9], [10, 12]] as [$desde, $hasta]) {
            $nombres = [];

            foreach (array_slice($meses, $desde - 1, $hasta - $desde + 1) as $mes) {
                foreach ($mes['cursos'] as $curso) {
                    $especialidad = trim((string) ($curso['especialidad'] ?? ''));

                    if ($especialidad !== '' && ! in_array($especialidad, $nombres, true)) {
                        $nombres[] = $especialidad;
                    }
                }
            }

            $etapas[] = [
                'numero' => count($etapas) + 1,
                'nombre' => $nombres === [] ? 'Tiempo para ponerte al día' : implode(' · ', array_slice($nombres, 0, 2)),
                'desde' => $desde,
                'hasta' => $hasta,
                'meses' => array_slice($meses, $desde - 1, $hasta - $desde + 1),
                'horas' => array_sum(array_column(array_slice($meses, $desde - 1, $hasta - $desde + 1), 'horas')),
                // Un curso que continúa se cuenta en la etapa donde empieza.
                'cursos' => count(array_filter(
                    array_merge(...array_column(array_slice($meses, $desde - 1, $hasta - $desde + 1), 'cursos')),
                    static fn (array $curso): bool => ! ($curso['continua'] ?? false),
                )),
            ];
        }

        return $etapas;
    }

    /** @param array<string, mixed> $doc */
    private function monthCalendarLabel(int $numero, array $doc): string
    {
        $inicio = $doc['membresia']['starts_on'] ?? null;

        if ($inicio === null) {
            return 'Mes '.$numero;
        }

        $fecha = Carbon::parse($inicio)->addMonthsNoOverflow($numero - 1);

        return self::MESES_CORTOS[$fecha->month].' '.$fecha->year;
    }

    /**
     * Marca en el inventario lo que el plan ya lleva. El inventario no repite el
     * itinerario: lo señala, y deja ver de un vistazo lo que queda abierto.
     *
     * @param  list<array<string, mixed>>  $grupos
     * @param  list<array<string, mixed>>  $itinerario
     * @return list<array<string, mixed>>
     */
    private function markPlanned(array $grupos, array $itinerario): array
    {
        $enPlan = array_flip(array_filter(array_map(
            fn (array $row): ?string => $row['slug'] ?? null,
            $itinerario,
        )));

        return array_map(fn (array $grupo): array => [
            ...$grupo,
            'en_plan' => count(array_filter($grupo['cursos'], fn (array $curso): bool => ! ($curso['horas_absorbidas'] ?? false) && isset($enPlan[$curso['slug'] ?? '']))),
            'programas' => count(array_filter($grupo['cursos'], fn (array $curso): bool => ! ($curso['horas_absorbidas'] ?? false))),
            'cursos' => array_map(
                fn (array $curso): array => [...$curso, 'en_plan' => isset($enPlan[$curso['slug'] ?? ''])],
                $grupo['cursos'],
            ),
        ], $grupos);
    }

    /**
     * La hoja «Quiénes somos» del gancho, decidida el 2026-09-24.
     *
     * Todo sale de `copy_blocks`, con lo que publica academy.dpsys.com.mx: cifras,
     * empresas, fotos de capacitación presencial y reseñas de Google. Las reseñas
     * que hablan de una especialidad de la ruta van primero; las generales
     * completan. Sin nada escrito la hoja no sale.
     *
     * @param  list<array<string, mixed>>  $itinerario
     * @return array<string, mixed>|null
     */
    private function socialProof(array $itinerario): ?array
    {
        $bloques = $this->copy->scoped('prueba_social')->keyBy('slug');

        $cifras = $bloques
            ->filter(fn ($bloque, string $slug): bool => str_starts_with($slug, 'confianza.cifra.'))
            ->map(function ($bloque): array {
                [$valor, $etiqueta] = array_pad(explode(' ', trim((string) $bloque->body), 2), 2, '');

                return ['valor' => $valor, 'etiqueta' => $etiqueta];
            })
            ->values()
            ->all();

        $empresas = array_map(fn (string $nombre): array => [
            'nombre' => $nombre,
            'logo' => $this->publicImage('img/dpsys/empresas/'.Str::slug($nombre).'.png'),
        ], $this->names($bloques->get('confianza.empresas')?->body));

        $fotos = array_values(array_filter(array_map(fn (string $nombre): ?array => ($ruta = $this->publicImage('img/dpsys/fotos/'.Str::slug($nombre).'.jpg')) === null
            ? null
            : ['nombre' => $nombre, 'src' => $ruta], $this->names($bloques->get('confianza.fotos')?->body))));

        $resenas = $this->reviews($bloques->all(), $itinerario);

        if ($cifras === [] && $empresas === [] && $resenas === []) {
            return null;
        }

        return [
            'entradilla' => $this->copy->body('confianza.entradilla'),
            'cifras' => $cifras,
            'empresas' => $empresas,
            'fotos' => $fotos,
            'pieFotos' => $fotos === [] ? null : $this->copy->body('confianza.pie-fotos', [
                'empresas' => $this->enumerate(array_column($fotos, 'nombre')),
            ]),
            'resenas' => array_map(function (array $resena): array {
                $frases = preg_split('/(?<=[.!?])\s+/u', $resena['texto']) ?: [$resena['texto']];

                return [...$resena, 'texto' => Str::words($frases[1] ?? $frases[0], 12, '…')];
            }, $resenas),
            'avales' => array_map(fn (array $aval): array => [
                ...$aval,
                'src' => $this->publicImage('img/dpsys/avales/'.$aval['logo'].'.png'),
                'url' => 'https://drive.google.com/file/d/'.$aval['documento'].'/view',
            ], config('dpsys.avales', [])),
            'cofundadores' => array_map(fn (array $fundador): array => [
                ...$fundador,
                'src' => $this->publicImage('img/dpsys/cofundadores/'.$fundador['foto']),
            ], config('dpsys.cofundadores', [])),
            'galeria' => array_map(fn (array $foto): array => [
                ...$foto,
                'src' => $this->publicImage('img/dpsys/fotos/'.$foto['archivo'].'.jpg'),
            ], config('dpsys.galeria', [])),
            'redes' => config('dpsys.redes', []),
            'fuentes' => config('dpsys.fuentes', []),
        ];
    }

    /**
     * Las reseñas que van, en el orden en que se leen: primero las que hablan de
     * una especialidad de esta ruta, luego las generales.
     *
     * @param  array<string, CopyBlock>  $bloques
     * @param  list<array<string, mixed>>  $itinerario
     * @return list<array{autor: string, texto: string}>
     */
    private function reviews(array $bloques, array $itinerario): array
    {
        $especialidades = array_values(array_unique(array_filter(array_column($itinerario, 'especialidad'))));
        $propias = [];
        $generales = [];

        foreach ($bloques as $slug => $bloque) {
            if (! str_starts_with($slug, 'resena.')) {
                continue;
            }

            [$autor, $texto] = array_pad(explode("\n", trim((string) $bloque->body), 2), 2, '');

            if (trim($texto) === '') {
                continue;
            }

            $resena = ['autor' => trim($autor), 'texto' => trim($texto)];
            $de = $bloque->applies_when['especialidad'] ?? null;

            if ($de === null) {
                $generales[] = $resena;
            } elseif (($indice = array_search($de, $especialidades, true)) !== false) {
                $propias[$indice] = $resena;
            }
        }

        ksort($propias);

        return array_slice([...array_values($propias), ...$generales], 0, self::RESENAS);
    }

    /** @return list<string> */
    private function names(?string $lista): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $lista))));
    }

    /** La URL de una imagen de `public/`, o null si el archivo no está. */
    private function publicImage(string $ruta): ?string
    {
        return is_file(public_path($ruta)) ? asset($ruta) : null;
    }

    /**
     * Reparte los bloques en hojas sin partir ninguno.
     *
     * Un bloque es una especialización entera con su encabezado: partirlo dejaría
     * media lista bajo un título y la otra media sin él, que es la mezcla de
     * encabezados que NO_HEADING_MERGE prohíbe. Si un bloque solo no cabe en una
     * hoja vacía, se le deja su hoja completa y se desborda ahí: es preferible a
     * partirlo.
     *
     * @param  list<array<string, mixed>>  $bloques
     * @return list<list<array<string, mixed>>>
     */
    private function paginate(array $bloques, float $primera, float $siguientes): array
    {
        if ($bloques === []) {
            return [];
        }

        $hojas = [];
        $actual = [];
        $usado = 0.0;
        $tope = $primera;

        // Mantiene juntos los tres cursos de refuerzo. El paginador del navegador
        // ya los trata como un paquete; hacerlo aquí también evita que Chromium
        // mande el pie de una hoja a otra y deje una página vacía al imprimir por
        // Gotenberg, que no dispara el evento beforeprint.
        $paquetes = [];

        foreach ($bloques as $bloque) {
            $refuerzo = ($bloque['rol'] ?? null) === 'complementaria';
            $ultimo = array_key_last($paquetes);

            if ($refuerzo && $ultimo !== null && $paquetes[$ultimo]['refuerzo']) {
                $paquetes[$ultimo]['bloques'][] = $bloque;
                $paquetes[$ultimo]['alto'] += $this->blockHeight($bloque) + self::ESPACIO_BLOQUE;
            } else {
                $paquetes[] = [
                    'refuerzo' => $refuerzo,
                    'bloques' => [$bloque],
                    'alto' => $this->blockHeight($bloque) + self::ESPACIO_BLOQUE,
                ];
            }
        }

        foreach ($paquetes as $paquete) {
            $alto = $paquete['alto'];
            $encabezado = $paquete['refuerzo'] ? self::ALTO_GRUPO_REFUERZOS : self::ALTO_GRUPO_PRINCIPAL;
            $cambiaGrupo = $actual === []
                || (($actual[array_key_last($actual)]['rol'] ?? null) === 'complementaria') !== $paquete['refuerzo'];
            $altoConGrupo = $alto + ($cambiaGrupo ? $encabezado : 0);

            if ($actual !== [] && $usado + $altoConGrupo > $tope) {
                $hojas[] = $actual;
                $actual = [];
                $usado = 0.0;
                $tope = $siguientes;
                $altoConGrupo = $alto + $encabezado;
            }

            array_push($actual, ...$paquete['bloques']);
            $usado += $altoConGrupo;
        }

        $hojas[] = $actual;

        return $hojas;
    }

    /**
     * Lo que ocupa un bloque: su encabezado con el margen que lo separa del
     * siguiente, más sus cursos. Un curso con frase de resultado ocupa dos
     * renglones de texto; sin ella, uno.
     *
     * @param  array<string, mixed>  $bloque
     */
    private function blockHeight(array $bloque): float
    {
        $alto = self::ALTO_ENCABEZADO_BLOQUE;

        foreach ($bloque['cursos'] as $curso) {
            $alto += filled($curso['frase_resultado'] ?? null)
                ? self::ALTO_CURSO
                : self::ALTO_CURSO_SIN_FRASE;

            if (filled($curso['motivo_recomendacion'] ?? null)) {
                // En los refuerzos el motivo sustituye la descripción corta.
                // Cuenta el marbete, el texto envuelto y el aire de la tarjeta.
                $alto += 24 + ceil(mb_strlen($curso['motivo_recomendacion']) / 88) * 17;
            }

            if ((int) ($curso['orden'] ?? 0) === 1 && ($curso['capa'] ?? 'itinerario') === 'itinerario') {
                $alto += self::ALTO_EMPIEZA;
            }
        }

        return $alto;
    }

    /**
     * Lo que el lead contó, para devolvérselo en la portada.
     *
     * Solo el contexto: la ocupación ya sale bajo el nombre y la especialización
     * ancla ya es el titular, así que repetirlas no persuade, estorba. Y es la
     * única personalización que el documento puede hacer sin inventar nada —son
     * palabras que el asesor capturó de la conversación—, así que un snapshot que
     * no las traiga simplemente no pinta el bloque.
     *
     * @param  array<string, mixed>  $doc
     * @param  list<array<string, mixed>>  $itinerario
     */
    private function why(array $doc, array $itinerario): ?string
    {
        if (($ajustado = $this->adjusted($doc, 'porque.con-contexto')) !== null) {
            return $ajustado;
        }

        return $this->editable->default('porque.con-contexto', [
            'contexto' => $doc['lead']['contexto'] ?? null,
            'primer_curso' => $itinerario[0]['nombre'] ?? null,
        ]);
    }

    /**
     * Las cifras del catálogo completo. Un snapshot anterior al rediseño no las
     * trae; el bloque de alcance de la membresía no se pinta y el documento se lee
     * igual, solo más corto.
     *
     * @param  array<string, mixed>  $doc
     * @return array<string, mixed>|null
     */
    private function catalog(array $doc): ?array
    {
        $catalogo = $doc['catalogo'] ?? null;

        return is_array($catalogo) && ($catalogo['especialidades'] ?? []) !== [] ? $catalogo : null;
    }

    private function roleLabel(?string $rol): ?string
    {
        // «Ancla» y «refuerzo» son lenguaje del catálogo, no del lead. Lo único
        // que se le dice es qué sumó el motor a lo que curó el embudo.
        return match ($rol) {
            'complementaria' => 'Sumado para tu perfil',
            default => null,
        };
    }
}
