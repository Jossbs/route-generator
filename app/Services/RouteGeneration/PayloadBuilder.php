<?php

declare(strict_types=1);

namespace App\Services\RouteGeneration;

use App\Enums\CourseLevel;
use App\Models\CatalogVersion;
use App\Models\Course;
use App\Models\Specialization;
use App\Services\Catalog\RouteTotals;
use App\Services\Documents\AudioScript;
use App\Services\Documents\WrittenMessage;
use App\Services\RouteGeneration\Data\RouteInput;
use App\Services\RouteValidation\Data\HeadingDraft;
use App\Services\RouteValidation\Data\RouteDraft;
use App\Services\RouteValidation\Data\ValidationReport;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Support\Collection;

/**
 * Congela el documento.
 *
 * El PDF se renderiza desde el payload, nunca desde el catálogo. Por eso aquí se
 * resuelve todo —nombres, horas, especialidades, vigencias, textos— y se escribe
 * completo: a partir de este punto el documento es inmutable.
 *
 * Cuatro razones lo sostienen:
 *
 *  1. El gancho vive en una URL permanente. Un lead que lo recibió en septiembre
 *     con «vigencia al 30 de septiembre» tiene que seguir viendo eso en enero.
 *  2. Si Eje Cursos edita el embudo mañana, los documentos de ayer no se mueven.
 *  3. El render se vuelve una función pura: recibe JSON, devuelve documento. Se
 *     puede probar sin base de datos.
 *  4. Si se corrigen las horas de un curso, queda trazabilidad de qué se le
 *     prometió a quién y bajo qué versión del catálogo.
 *
 * El costo son unos pocos KB de JSON por emisión.
 */
final class PayloadBuilder
{
    public const SCHEMA_VERSION = '1.0';

    public function __construct(
        private readonly AudioScript $audioScript,
        private readonly WrittenMessage $writtenMessage,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(RouteInput $input, RouteDraft $draft, ValidationReport $report): array
    {
        $courses = $this->courses($draft);
        $specializations = $this->specializations($draft, $courses);
        $totals = RouteTotals::for($courses->values());

        $payload = [
            'schema_version' => self::SCHEMA_VERSION,
            'generado_en' => now()->toIso8601String(),
            'catalogo_version' => CatalogVersion::current()->label,
            'tipo_documento' => $draft->documentTypeSlug,

            'lead' => [
                'nombre' => $input->lead->name,
                'telefono' => $input->lead->phone,
                'email' => $input->lead->email,
                'kommo_id' => $input->lead->external_id,
                // Los dos campos que el asesor captura de la conversación. Viajan
                // porque el documento se los devuelve al lead con sus palabras:
                // es lo que demuestra que alguien lo escuchó, y no hay nada que
                // inventar para escribirlo.
                'ocupacion' => $input->lead->occupation,
                'contexto' => $input->lead->context,
            ],

            'asesor' => $input->lead->advisor === null ? null : [
                'id' => $input->lead->advisor->id,
                'nombre' => $input->lead->advisor->name,
                // El cierre del gancho enlaza aquí. Viaja congelado como todo lo
                // demás: si el asesor cambia de número mañana, el documento que
                // el lead ya recibió sigue llevando al que se le prometió.
                'whatsapp' => $input->lead->advisor->whatsapp,
            ],

            'embudo' => $this->funnel($input),

            'ruta' => [
                'nombre' => $input->funnelRoute?->name,
                'arquetipo' => $input->archetype?->name,
                'especialidad_principal' => $draft->anchorSpecializationId === null
                    ? null
                    : $specializations->get($draft->anchorSpecializationId)?->name,
                'duracion_meses' => $input->durationMonths,
                'texto_intro' => $input->funnelRoute?->intro_text,
                'texto_cierre' => $input->funnelRoute?->closing_text,
                'encabezados' => $this->headings($draft, $specializations),
                'cursos' => $this->courseRows($draft, $courses, $specializations, $totals),
                'totales' => $totals->toArray(),
            ],

            'membresia' => $draft->membership,
            'copy' => $draft->copyText,

            // Los párrafos que el asesor ajustó en la revisión, ya escaneados.
            // El documento los imprime en lugar del texto de catálogo, y una
            // corrección los recupera de aquí.
            'ajustes_copy' => $input->copyOverrides,

            // El catálogo entero, congelado igual que la ruta. No es la capa de
            // inventario —esa solo la lleva el anual— sino las cifras que
            // docs/ARQUITECTURA.md §4.2 obliga a declarar con la contención dicha: el lead
            // ve cuántos programas tiene su ruta y a cuántos le abre la
            // membresía. Calculadas, nunca tecleadas.
            'catalogo' => $this->catalog(),

            // Null a propósito, y no por falta de dato: desde el 2026-09-17 el
            // asesor sí captura por cuánto se cerró la membresía. Ese importe vive
            // en `memberships` y se queda ahí.
            //
            // El snapshot es lo que alimenta al PDF, así que lo que entre aquí es
            // imprimible, y el documento no imprime precios: los cuantifica en
            // horas, programas y constancias DC-3, y el precio lo dice el asesor en
            // la conversación, donde puede atender objeciones (docs/ARQUITECTURA.md §4.5).
            // Si algún día se decide imprimirlo, se decide arriba —en la
            // restricción— no aquí.
            'precios' => null,

            // Las advertencias que quien publicó confirmó explícitamente. Se
            // guardan para que después se pueda auditar quién sabía qué.
            'avisos_validador' => $this->warnings($report, $input),
        ];

        // El guión del audio que acompaña al gancho, y el mensaje escrito que lo
        // sustituye cuando el asesor no puede grabar. Se arman con la ruta ya
        // compuesta para que digan lo mismo que el PDF, y se congelan aquí para
        // que lo que ya se mandó siga siendo reproducible.
        if ($draft->documentTypeSlug === DocumentTypeSeeder::ENFOCADA) {
            $payload['guion'] = $this->audioScript->for($payload, $input->lead);
            $payload['mensaje'] = $this->writtenMessage->for($payload, $input->lead);
        }

        return $payload;
    }

    /**
     * El sello: detecta que el payload se tocó después de emitirse.
     *
     * @param  array<string, mixed>  $payload
     */
    public function hash(array $payload): string
    {
        return PayloadHash::of($payload);
    }

    /**
     * Las cifras del catálogo completo, con los mismos totales netos que usa una
     * ruta: los tres paquetes «Completo» absorben a sus niveles, así que 64 cursos
     * equivalen a 55 programas de contenido distintos. Si el lead suma, cuadra.
     *
     * @return array<string, mixed>
     */
    private function catalog(): array
    {
        $courses = Course::query()->where('is_active', true)->get();
        $totals = RouteTotals::for($courses);

        return [
            'cursos' => $totals->courseCount,
            'programas' => $totals->contentUnitCount,
            'horas' => $totals->netHours,
            'especialidades' => Specialization::query()
                ->orderBy('display_order')
                ->pluck('name')
                ->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function funnel(RouteInput $input): ?array
    {
        $funnel = $input->funnel;

        if ($funnel === null) {
            return null;
        }

        return [
            'slug' => $funnel->slug,
            // Es el título de la portada del gancho (decidido el 2026-09-24): el
            // lead llegó por un anuncio con ese nombre. Desde el 2026-09-14 el
            // embudo tiene un solo nombre —el nombre público quedó dormido—, así
            // que ese es el que viaja cuando no hay uno público.
            'nombre_publico' => $funnel->public_name ?? $funnel->internal_name,
            'angulo' => $funnel->angle?->value,
            'headline' => $funnel->headline,
            'subheadline' => $funnel->subheadline,
            'vigencia_inicio' => $funnel->starts_on?->toDateString(),
            'vigencia_fin' => $funnel->ends_on?->toDateString(),
        ];
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @param  Collection<int, Specialization>  $specializations
     * @return list<array<string, mixed>>
     */
    private function courseRows(
        RouteDraft $draft,
        Collection $courses,
        Collection $specializations,
        RouteTotals $totals,
    ): array {
        $rows = [];

        foreach ($draft->items as $index => $item) {
            $course = $courses->get($item->courseId);

            if ($course === null) {
                continue;
            }

            $rows[] = [
                'orden' => $index + 1,
                'mes_sugerido' => $item->month,
                // Solo cuando el curso no cabe en un mes a su ritmo.
                'mes_fin' => $item->monthEnd,
                'nombre' => $course->name,
                'slug' => $course->slug,
                'especialidad' => $specializations->get($item->specializationId)?->name,
                'horas' => $course->duration_hours,
                // Nunca se imprime null como promesa: si Contenido todavía no
                // aprobó la frase, el documento lleva la descripción que el propio
                // sitio publica, y el estado dice cuál de las dos es.
                'frase_resultado' => $course->outcome_phrase,
                'frase_resultado_estado' => $course->outcome_phrase_status,
                'capa' => $item->layer->value,
                'rol' => $item->role->value,
                'tipo' => 'fijo',
                // El nivel se imprime como distintivo del curso. Es lo que deja
                // que la ruta se lea como una progresión y no como una lista, y
                // es el mismo dato sobre el que LEVEL_SEQUENCE se evalúa.
                'nivel' => $course->level->value,
                // La unidad de contenido a la que pertenece. Es lo que deja
                // colgar cada nivel absorbido de su paquete al presentarlos: sin
                // ella, el «↳» del inventario no apunta a nada.
                'unidad' => $course->content_unit_id,
                'es_paquete' => $course->level === CourseLevel::Completo,
                // El curso cuyas horas ya cubre un paquete presente en la ruta.
                'horas_absorbidas' => in_array((int) $course->id, $totals->absorbedCourseIds, true),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Specialization>  $specializations
     * @return list<array<string, mixed>>
     */
    private function headings(RouteDraft $draft, Collection $specializations): array
    {
        return array_map(fn (HeadingDraft $heading): array => [
            'clave' => $heading->key,
            'titulo' => $heading->label,
            'mes' => $heading->month,
            'especialidades' => array_map(
                fn (int $id): ?string => $specializations->get($id)?->name,
                $heading->specializationIds,
            ),
        ], $draft->headings);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function warnings(ValidationReport $report, RouteInput $input): array
    {
        return array_values($report->warnings()
            ->map(fn ($evaluation): array => [
                'codigo' => $evaluation->ruleCode,
                'mensaje' => $evaluation->message ?? $evaluation->ruleCode,
                'confirmado' => in_array($evaluation->ruleCode, $input->acknowledgedWarnings, true),
                'detalles' => $evaluation->details,
            ])
            ->all());
    }

    /** @return Collection<int, Course> */
    private function courses(RouteDraft $draft): Collection
    {
        return Course::query()->whereIn('id', $draft->courseIds())->get()->keyBy('id');
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @return Collection<int, Specialization>
     */
    private function specializations(RouteDraft $draft, Collection $courses): Collection
    {
        $ids = array_values(array_unique([
            ...$draft->specializationIds(),
            ...$courses->pluck('specialization_id')->map(intval(...))->all(),
        ]));

        return Specialization::query()->whereIn('id', $ids)->get()->keyBy('id');
    }
}
