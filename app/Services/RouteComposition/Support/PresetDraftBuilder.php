<?php

declare(strict_types=1);

namespace App\Services\RouteComposition\Support;

use App\Enums\RouteItemRole;
use App\Enums\RouteLayer;
use App\Models\Course;
use App\Models\DocumentType;
use App\Services\Catalog\RouteTotals;
use App\Services\RouteValidation\Data\HeadingDraft;
use App\Services\RouteValidation\Data\RouteDraft;
use App\Services\RouteValidation\Data\RouteItemDraft;
use App\Services\RouteValidation\Data\RuleWaivers;
use Illuminate\Support\Collection;

/**
 * Arma un borrador desde una lista de cursos ya elegida, en vez de componerla
 * desde el catálogo.
 *
 * Es lo que hace que el embudo sea un preset y no un motor paralelo: la ruta que
 * Eje Cursos curó, la que el asesor precargó y después modificó, y la que se
 * valida en vivo dentro del editor son la misma estructura, y las tres llegan al
 * mismo validador. Sin esto habría que reimplementar las restricciones de
 * docs/ARQUITECTURA.md §4 sobre otra forma, y dos copias de una restricción se separan tarde
 * o temprano.
 *
 * ### El mapeo de roles
 *
 * Lo decide el origen del curso, no su especialidad. Todo lo que trae el embudo
 * es ruta principal —`ancla` los de la especialidad que lo abre, `refuerzo` los
 * demás, que también van tal como se curaron— y lo que el motor sumó encima es
 * `complementaria`, que es el único rol que el asesor puede podar.
 *
 * La distinción importa fuera de la pantalla: `complementary_*_specialization_id`
 * se llena de los roles complementarios, y las reglas que se evalúan «sobre lo
 * recomendado» miran el rol, no de dónde salió el renglón.
 *
 * No es cosmético: es lo que mantiene vivas las restricciones de presentación
 * sobre una lista curada a mano.
 *
 * Cadena de Suministros lo demuestra. Su ficha declara `ancla: false` y
 * `bloque_itinerario: true`, así que una ruta que la ponga de principal falla
 * —tiene un solo curso y no se presenta sola— mientras que una que la incluya
 * acompañando a Lean o a Kaizen pasa, cada una bajo su propio título. Es
 * exactamente la restricción, y ninguna línea de código la nombra.
 */
final class PresetDraftBuilder
{
    /**
     * @param  list<int>  $courseIds  En el orden en que se presentarán.
     * @param  array<int, int|null>  $monthsByCourseId  Mes sugerido por curso; ausente = sin mes.
     * @param  list<string>  $copyText  Texto de cara al lead, para el escaneo de fraseo.
     * @param  array<string, mixed>|null  $membership  Datos de membresía, si el documento los exige.
     * @param  string|null  $presetName  El nombre de la ruta curada de la que salió
     *                                   la lista, para que cada bloque diga de dónde
     *                                   viene. Lo lee el asesor, nunca el lead.
     * @param  array<int, string>  $justificationsByCourseId  El porqué de los cursos que
     *                                                        no vinieron del embudo —los que el motor sumó para
     *                                                        llegar al mínimo de recomendados—. Sin esto dirían
     *                                                        que salieron de una campaña que no los eligió.
     * @param  list<int>|null  $curatedCourseIds  Los cursos del embudo, que son la ruta
     *                                            principal y no se tocan. null = no hubo embudo
     *                                            y la lista entera se trata como curada.
     */
    public function build(
        DocumentType $documentType,
        array $courseIds,
        ?int $anchorSpecializationId = null,
        array $monthsByCourseId = [],
        array $copyText = [],
        ?array $membership = null,
        ?string $presetName = null,
        array $justificationsByCourseId = [],
        ?array $curatedCourseIds = null,
        ?RuleWaivers $waivers = null,
        bool $advisorCanChooseExtras = false,
    ): RouteDraft {
        $courses = $this->courses($courseIds);
        $anchorId = $anchorSpecializationId ?? $courses->first()?->specialization_id;
        $totals = RouteTotals::for($courses);

        return new RouteDraft(
            documentTypeSlug: $documentType->slug,
            items: $this->items(
                $courses,
                $anchorId,
                $monthsByCourseId,
                $presetName,
                $justificationsByCourseId,
                $curatedCourseIds,
            ),
            anchorSpecializationId: $anchorId === null ? null : (int) $anchorId,
            headings: $this->headings($courses, $monthsByCourseId),
            copyText: $copyText,
            membership: $membership,
            declaredCourseCount: $totals->courseCount,
            declaredContentUnitCount: $totals->contentUnitCount,
            waivers: $waivers,
            advisorCanChooseExtras: $advisorCanChooseExtras,
        );
    }

    /**
     * Los cursos en el orden pedido. Un id que ya no exista en el catálogo se cae
     * de la lista: es mejor que el validador evalúe la ruta que sí se puede armar
     * a que reviente resolviendo un curso borrado.
     *
     * @param  list<int>  $courseIds
     * @return Collection<int, Course>
     */
    public function courses(array $courseIds): Collection
    {
        $byId = Course::query()
            ->with('specialization')
            ->whereIn('id', $courseIds)
            ->get()
            ->keyBy('id');

        return collect($courseIds)
            ->unique()
            ->map(fn (int $id): ?Course => $byId->get($id))
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @param  array<int, int|null>  $monthsByCourseId
     * @param  array<int, string>  $justificationsByCourseId
     * @param  list<int>|null  $curatedCourseIds
     * @return list<RouteItemDraft>
     */
    private function items(
        Collection $courses,
        ?int $anchorId,
        array $monthsByCourseId,
        ?string $presetName = null,
        array $justificationsByCourseId = [],
        ?array $curatedCourseIds = null,
    ): array {
        $justification = $presetName === null
            ? null
            : "De la ruta «{$presetName}», armada para esta campaña.";

        // Sin embudo no hay nada que distinguir: la lista entera llegó curada.
        $curados = $curatedCourseIds === null
            ? null
            : array_flip($curatedCourseIds);

        return array_values($courses
            ->values()
            ->map(fn (Course $course, int $index): RouteItemDraft => new RouteItemDraft(
                courseId: (int) $course->id,
                specializationId: (int) $course->specialization_id,
                // Una lista curada es un itinerario recomendado. El inventario de
                // la membresía es otra capa y otro documento.
                layer: RouteLayer::Itinerario,
                role: $this->role($course, $anchorId, $curados),
                position: $index + 1,
                month: $monthsByCourseId[$course->id] ?? null,
                justification: $justificationsByCourseId[$course->id] ?? ($curados !== null && ! isset($curados[$course->id]) ? 'Seleccionado por el asesor.' : $justification),
            ))
            ->all());
    }

    /**
     * El rol de un renglón, decidido por de dónde salió.
     *
     * Un curso del embudo es ruta principal aunque sea de otra especialidad: la
     * campaña lo eligió y el documento lo lleva tal cual. Uno que el motor sumó
     * después es una recomendación, y por eso es lo único que el asesor poda.
     *
     * @param  array<int, int>|null  $curados  Curso => posición, o null si no hubo embudo.
     */
    private function role(Course $course, ?int $anchorId, ?array $curados): RouteItemRole
    {
        if ($curados !== null && ! isset($curados[(int) $course->id])) {
            return RouteItemRole::Complementaria;
        }

        return (int) $course->specialization_id === $anchorId
            ? RouteItemRole::Ancla
            : RouteItemRole::Refuerzo;
    }

    /**
     * Un encabezado por especialidad presente, con la suya y nada más.
     *
     * La estructura hace imposible mezclarlas. El validador lo comprueba de todos
     * modos: una restricción que solo se cumple por construcción deja de cumplirse
     * el día que la construcción cambia.
     *
     * @param  Collection<int, Course>  $courses
     * @param  array<int, int|null>  $monthsByCourseId
     * @return list<HeadingDraft>
     */
    private function headings(Collection $courses, array $monthsByCourseId): array
    {
        return array_values($courses
            ->groupBy('specialization_id')
            ->map(fn (Collection $group, int|string $specializationId): HeadingDraft => new HeadingDraft(
                key: 'especialidad:'.$specializationId,
                label: (string) $group->first()->specialization?->name,
                specializationIds: [(int) $specializationId],
                month: $this->firstMonth($group, $monthsByCourseId),
            ))
            ->all());
    }

    /**
     * @param  Collection<int, Course>  $group
     * @param  array<int, int|null>  $monthsByCourseId
     */
    private function firstMonth(Collection $group, array $monthsByCourseId): ?int
    {
        $months = $group
            ->map(fn (Course $course): ?int => $monthsByCourseId[$course->id] ?? null)
            ->filter(fn (?int $month): bool => $month !== null);

        return $months->isEmpty() ? null : (int) $months->min();
    }
}
