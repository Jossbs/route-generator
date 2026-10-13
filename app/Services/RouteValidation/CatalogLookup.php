<?php

declare(strict_types=1);

namespace App\Services\RouteValidation;

use App\Models\BannedPhrase;
use App\Models\Course;
use App\Models\Specialization;
use App\Services\RouteValidation\Data\RouteDraft;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resuelve el catálogo que toca la ruta, una sola vez, para que los ocho
 * intérpretes no vuelvan a la base cada uno por su cuenta.
 */
final class CatalogLookup
{
    /** @var Collection<int, Course>|null */
    private ?Collection $courses = null;

    /** @var Collection<int, Specialization>|null */
    private ?Collection $specializations = null;

    /** @var array<string, array<int, list<int>>>|null Clave de fuerzas => (curso => prerrequisitos). */
    private ?array $hardPrerequisites = null;

    /** @var Collection<int, BannedPhrase>|null */
    private ?Collection $bannedPhrases = null;

    /** @var list<array{parent: int, child: int, type: string}>|null */
    private ?array $containments = null;

    public function __construct(private readonly RouteDraft $draft) {}

    /** @return Collection<int, Course> */
    public function courses(): Collection
    {
        return $this->courses ??= Course::query()
            ->whereIn('id', $this->draft->courseIds())
            ->get()
            ->keyBy('id');
    }

    public function course(int $id): ?Course
    {
        return $this->courses()->get($id);
    }

    /** @return Collection<int, Specialization> */
    public function specializations(): Collection
    {
        return $this->specializations ??= Specialization::query()
            ->whereIn('id', array_values(array_unique([
                ...$this->draft->specializationIds(),
                ...$this->courses()->pluck('specialization_id')->all(),
            ])))
            ->get()
            ->keyBy('id');
    }

    public function specialization(int $id): ?Specialization
    {
        return $this->specializations()->get($id);
    }

    public function contentUnitId(int $courseId): ?int
    {
        return $this->course($courseId)?->content_unit_id;
    }

    /**
     * @param  list<string>  $strengths
     * @return array<int, list<int>>
     */
    public function prerequisites(array $strengths): array
    {
        $key = implode('|', $strengths);

        if ($this->hardPrerequisites === null || ! array_key_exists($key, $this->hardPrerequisites)) {
            $rows = DB::table('course_prerequisites')
                ->whereIn('course_id', $this->draft->courseIds())
                ->whereIn('strength', $strengths)
                ->get(['course_id', 'requires_course_id']);

            $map = [];
            foreach ($rows as $row) {
                $map[(int) $row->course_id][] = (int) $row->requires_course_id;
            }

            $this->hardPrerequisites[$key] = $map;
        }

        return $this->hardPrerequisites[$key];
    }

    /**
     * Los pares paquete → nivel absorbido que tocan a esta ruta.
     *
     * Sale de `course_containments`, que es donde el catálogo declara qué absorbe
     * qué. Ningún nombre de paquete aparece en el código: confirmar una contención
     * nueva es una fila, no un deploy.
     *
     * @return list<array{parent: int, child: int, type: string}>
     */
    public function containments(): array
    {
        if ($this->containments !== null) {
            return $this->containments;
        }

        $courseIds = $this->draft->courseIds();

        $rows = DB::table('course_containments')
            ->whereIn('parent_course_id', $courseIds)
            ->whereIn('child_course_id', $courseIds)
            ->get(['parent_course_id', 'child_course_id', 'containment_type']);

        return $this->containments = array_values($rows
            ->map(fn ($row): array => [
                'parent' => (int) $row->parent_course_id,
                'child' => (int) $row->child_course_id,
                'type' => (string) $row->containment_type,
            ])
            ->all());
    }

    /**
     * @param  list<string>|null  $scopes  null = todos los ámbitos activos.
     * @return Collection<int, BannedPhrase>
     */
    public function bannedPhrases(?array $scopes): Collection
    {
        $all = $this->bannedPhrases ??= BannedPhrase::query()->active()->get();

        return $scopes === null
            ? $all
            : $all->filter(fn (BannedPhrase $p): bool => in_array($p->scope, $scopes, true))->values();
    }
}
