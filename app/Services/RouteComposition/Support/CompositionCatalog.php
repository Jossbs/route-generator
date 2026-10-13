<?php

declare(strict_types=1);

namespace App\Services\RouteComposition\Support;

use App\Enums\CourseLevel;
use App\Enums\RouteLayer;
use App\Models\Archetype;
use App\Models\Course;
use App\Models\Specialization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El catálogo que el compositor recorre, cargado una vez por solicitud.
 *
 * Distingue dos universos que no son el mismo: lo recomendable —lo que puede
 * entrar al itinerario— y lo incluido —todo lo que la membresía cubre, que es lo
 * que lista el inventario del documento anual.
 */
final class CompositionCatalog
{
    /** @var Collection<int, Course>|null */
    private ?Collection $recommendable = null;

    /** @var Collection<int, Course>|null */
    private ?Collection $included = null;

    /** @var Collection<int, Specialization>|null */
    private ?Collection $specializations = null;

    /** @var array<int, float>|null */
    private ?array $specializationAffinity = null;

    public function __construct(private readonly ?Archetype $archetype = null) {}

    /**
     * Los cursos que el generador puede proponer como recomendación.
     *
     * @return Collection<int, Course>
     */
    public function recommendable(): Collection
    {
        return $this->recommendable ??= Course::query()
            ->recommendable()
            ->with('tags:id')
            ->get()
            ->keyBy('id');
    }

    /**
     * Todo lo que la membresía incluye, sin filtrar: el inventario del documento
     * anual lista el catálogo completo, incluidos los cursos que no se recomiendan.
     *
     * @return Collection<int, Course>
     */
    public function included(): Collection
    {
        return $this->included ??= Course::query()
            ->active()
            ->get()
            ->filter(fn (Course $course): bool => $course->allowsLayer(RouteLayer::Inventario))
            ->keyBy('id');
    }

    /**
     * Los cursos recomendables de una especialización.
     *
     * `$expandPackages` decide cómo se resuelve un paquete «Completo» frente a sus
     * propios niveles, que nunca conviven. Es una decisión de presentación, no de
     * catálogo, y por eso la toma quien arma el bloque:
     *
     *   - Expandido, para el bloque ancla. «Microsoft Excel Completo» solo es un
     *     renglón; Básico, Intermedio y Avanzado son tres y se leen como progresión.
     *     Las horas son las mismas y el programa sigue siendo uno.
     *   - Condensado, para una complementaria. Ahí caben dos o tres renglones y el
     *     paquete dice en uno lo que los niveles dirían en tres.
     *
     * @return Collection<int, Course>
     */
    public function recommendableIn(Specialization $specialization, bool $expandPackages = false): Collection
    {
        return $this->resolvePackages(
            $this->recommendable()->where('specialization_id', $specialization->id),
            $expandPackages,
        );
    }

    /**
     * Resuelve cada familia «Completo» a una sola forma: o el paquete, o sus
     * niveles, nunca ambos.
     *
     * Sin esto la elección la decidiría el peso curso por curso, y el resultado
     * sería incoherente entre familias: el documento mostraría SolidWorks
     * desglosado en tres renglones y AutoCAD condensado en uno, sin más razón que
     * un empate resuelto por decimales.
     *
     * @param  Collection<int, Course>  $courses
     * @return Collection<int, Course>
     */
    public function resolvePackages(Collection $courses, bool $expandPackages): Collection
    {
        $unitsWithLevels = $courses
            ->filter(fn (Course $course): bool => $course->level !== CourseLevel::Completo && $course->level !== CourseLevel::Unico)
            ->pluck('content_unit_id')
            ->unique();

        if ($unitsWithLevels->isEmpty()) {
            return $courses;
        }

        return $courses->filter(fn (Course $course): bool => ! $unitsWithLevels->contains($course->content_unit_id)
            || ($expandPackages xor $course->level === CourseLevel::Completo));
    }

    /** @return Collection<int, Specialization> */
    public function specializations(): Collection
    {
        return $this->specializations ??= Specialization::query()
            ->orderBy('display_order')
            ->get()
            ->keyBy('id');
    }

    public function specialization(int $id): ?Specialization
    {
        return $this->specializations()->get($id);
    }

    /**
     * Las especializaciones que pueden ocupar un rol de presentación, ordenadas
     * por afinidad al arquetipo. La lista blanca sale de `allowed_roles`: Cadena
     * de Suministros no aparece como ancla ni como complementaria sin que este
     * método la nombre.
     *
     * @param  list<int>  $excludeIds
     * @return list<Specialization>
     */
    public function eligibleFor(string $presentationRole, array $excludeIds = []): array
    {
        return array_values($this->specializations()
            ->reject(fn (Specialization $s): bool => in_array($s->id, $excludeIds, true))
            ->filter(fn (Specialization $s): bool => $s->allowsRole($presentationRole))
            // Una especialización sin cursos recomendables no puede sostener un
            // bloque, aunque su ficha permita el rol.
            ->filter(fn (Specialization $s): bool => $this->recommendableIn($s)->isNotEmpty())
            ->sortBy(fn (Specialization $s): array => [-$this->affinityFor($s), $s->display_order])
            ->values()
            ->all());
    }

    /**
     * Afinidad del arquetipo con la especialización, de 0 a 1.
     * v1 siembra un solo arquetipo placeholder sin afinidades por especialización:
     * hasta que llegue el Excel, esto devuelve cero y manda `display_order`.
     */
    public function affinityFor(Specialization $specialization): float
    {
        // Igual que en CourseRanker: de la afinidad solo interesa el peso, así que
        // se lee la tabla directa en lugar de hidratar los modelos.
        $this->specializationAffinity ??= $this->archetype === null
            ? []
            : DB::table('archetype_specialization_affinity')
                ->where('archetype_id', $this->archetype->id)
                ->pluck('weight', 'specialization_id')
                ->map(floatval(...))
                ->all();

        return $this->specializationAffinity[$specialization->id] ?? 0.0;
    }
}
