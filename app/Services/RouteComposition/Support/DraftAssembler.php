<?php

declare(strict_types=1);

namespace App\Services\RouteComposition\Support;

use App\Enums\RouteItemRole;
use App\Enums\RouteLayer;
use App\Models\Course;
use App\Models\Specialization;
use App\Services\RouteValidation\Data\HeadingDraft;
use App\Services\RouteValidation\Data\RouteItemDraft;

/**
 * Acumula los renglones y encabezados de una ruta mientras el compositor la arma.
 *
 * Resuelve lo que los dos compositores harían igual y por separado: la posición
 * correlativa, un encabezado por especialización y mes —que es lo que
 * NO_HEADING_MERGE verifica— y las dos cifras que el documento declara.
 *
 * Esas cifras se calculan igual que las recalcula el validador: cursos distintos
 * y programas de contenido distintos. Los cuatro cursos de Excel son cuatro
 * renglones y un solo programa, y de ahí sale «64 cursos · 55 programas».
 */
final class DraftAssembler
{
    /** @var list<RouteItemDraft> */
    private array $items = [];

    /** @var array<string, HeadingDraft> */
    private array $headings = [];

    /** @var array<int, Course> Curso => modelo, para las cifras y las horas. */
    private array $courses = [];

    private int $position = 0;

    /**
     * @param  list<Course>  $courses
     * @param  array<int, int>  $monthEnds  Curso => mes en que termina.
     */
    public function block(
        Specialization $specialization,
        array $courses,
        RouteItemRole $role,
        RouteLayer $layer = RouteLayer::Itinerario,
        ?int $month = null,
        ?string $justification = null,
        array $monthEnds = [],
    ): self {
        if ($courses === []) {
            return $this;
        }

        if ($layer === RouteLayer::Itinerario) {
            $this->heading($specialization, $month);
        }

        foreach ($courses as $course) {
            $this->courses[$course->id] = $course;

            $this->items[] = new RouteItemDraft(
                courseId: $course->id,
                specializationId: $course->specialization_id,
                layer: $layer,
                role: $role,
                position: ++$this->position,
                month: $month,
                justification: $justification,
                monthEnd: $monthEnds[$course->id] ?? null,
            );
        }

        return $this;
    }

    /** @return list<RouteItemDraft> */
    public function items(): array
    {
        return $this->items;
    }

    /** @return list<HeadingDraft> */
    public function headings(): array
    {
        return array_values($this->headings);
    }

    /** Cursos distintos de la ruta: la primera de las dos cifras que el PDF declara. */
    public function courseCount(): int
    {
        return count($this->countedCourses());
    }

    /**
     * Programas de contenido distintos: la segunda cifra. Es menor que la anterior
     * siempre que la ruta lleve un paquete «Completo» junto a alguno de sus niveles.
     */
    public function contentUnitCount(): int
    {
        return count(array_unique(array_map(
            fn (Course $course): int => $course->content_unit_id,
            $this->countedCourses(),
        )));
    }

    /**
     * Horas del itinerario, contadas una vez por programa. Si un día conviven un
     * paquete y uno de sus niveles, las 36 h del paquete no se suman dos veces.
     */
    public function itineraryHours(): int
    {
        $hoursByUnit = [];

        foreach ($this->itinerary() as $item) {
            $course = $this->courses[$item->courseId];

            $hoursByUnit[$course->content_unit_id] = max(
                $hoursByUnit[$course->content_unit_id] ?? 0,
                $course->duration_hours ?? 0,
            );
        }

        return (int) array_sum($hoursByUnit);
    }

    /**
     * Los cursos que entran a las cifras declaradas, deduplicados.
     *
     * @return array<int, Course>
     */
    private function countedCourses(): array
    {
        $counted = [];

        foreach ($this->items as $item) {
            if ($item->countsTowardTotal) {
                $counted[$item->courseId] = $this->courses[$item->courseId];
            }
        }

        return $counted;
    }

    /** @return list<RouteItemDraft> */
    private function itinerary(): array
    {
        return array_values(array_filter(
            $this->items,
            fn (RouteItemDraft $item): bool => $item->layer === RouteLayer::Itinerario,
        ));
    }

    /**
     * Un encabezado por especialización, y por mes cuando el documento se reparte
     * en meses. Dos especializaciones pueden compartir un mes, jamás un título.
     */
    private function heading(Specialization $specialization, ?int $month): void
    {
        $key = $month === null
            ? "esp-{$specialization->id}"
            : "mes-{$month}-esp-{$specialization->id}";

        $this->headings[$key] ??= new HeadingDraft(
            key: $key,
            label: $specialization->name,
            specializationIds: [$specialization->id],
            month: $month,
        );
    }
}
