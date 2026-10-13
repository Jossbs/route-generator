<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\RouteItemRole;
use App\Enums\RouteLayer;
use App\Models\Course;
use App\Models\Specialization;
use App\Services\RouteValidation\Data\HeadingDraft;
use App\Services\RouteValidation\Data\RouteDraft;
use App\Services\RouteValidation\Data\RouteItemDraft;
use Database\Seeders\DocumentTypeSeeder;

/**
 * Arma borradores de ruta a partir de slugs del catálogo sembrado, para que las
 * pruebas se lean como el caso de negocio y no como una lista de identificadores.
 */
final class DraftBuilder
{
    /** @var list<RouteItemDraft> */
    private array $items = [];

    /** @var list<HeadingDraft> */
    private array $headings = [];

    /** @var list<string> */
    private array $copyText = [];

    private ?array $membership = null;

    private ?int $anchorSpecializationId = null;

    private ?int $declaredCourseCount = null;

    private ?int $declaredContentUnitCount = null;

    private int $nextPosition = 1;

    private function __construct(private readonly string $documentTypeSlug) {}

    public static function enfocada(): self
    {
        return new self(DocumentTypeSeeder::ENFOCADA);
    }

    public static function anual(): self
    {
        return new self(DocumentTypeSeeder::ANUAL);
    }

    public function anchor(string $specializationSlug): self
    {
        $this->anchorSpecializationId = self::specializationId($specializationSlug);

        return $this;
    }

    public function course(
        string $courseSlug,
        RouteItemRole $role = RouteItemRole::Complementaria,
        RouteLayer $layer = RouteLayer::Itinerario,
        ?int $month = null,
        bool $countsTowardTotal = true,
        ?int $position = null,
    ): self {
        $course = self::findCourse($courseSlug);

        $this->items[] = new RouteItemDraft(
            courseId: $course->id,
            specializationId: $course->specialization_id,
            layer: $layer,
            role: $role,
            position: $position ?? $this->nextPosition++,
            month: $month,
            countsTowardTotal: $countsTowardTotal,
        );

        return $this;
    }

    /** @param list<string> $courseSlugs */
    public function courses(array $courseSlugs, RouteItemRole $role = RouteItemRole::Complementaria, RouteLayer $layer = RouteLayer::Itinerario, ?int $month = null): self
    {
        foreach ($courseSlugs as $slug) {
            $this->course($slug, $role, $layer, $month);
        }

        return $this;
    }

    /** Todo el catálogo activo como inventario de la membresía. */
    public function fullInventory(): self
    {
        foreach (Course::query()->active()->get() as $course) {
            $this->items[] = new RouteItemDraft(
                courseId: $course->id,
                specializationId: $course->specialization_id,
                layer: RouteLayer::Inventario,
                role: RouteItemRole::Incluido,
                position: $this->nextPosition++,
            );
        }

        return $this;
    }

    /** @param list<string> $specializationSlugs */
    public function heading(string $label, array $specializationSlugs, ?int $month = null): self
    {
        $this->headings[] = new HeadingDraft(
            key: str($label)->slug()->value(),
            label: $label,
            specializationIds: array_map(self::specializationId(...), $specializationSlugs),
            month: $month,
        );

        return $this;
    }

    public function copy(string ...$blocks): self
    {
        $this->copyText = [...$this->copyText, ...$blocks];

        return $this;
    }

    /** @param array<string, mixed> $data */
    public function membership(array $data): self
    {
        $this->membership = $data;

        return $this;
    }

    public function declaring(?int $courses, ?int $contentUnits): self
    {
        $this->declaredCourseCount = $courses;
        $this->declaredContentUnitCount = $contentUnits;

        return $this;
    }

    public function build(): RouteDraft
    {
        return new RouteDraft(
            documentTypeSlug: $this->documentTypeSlug,
            items: $this->items,
            anchorSpecializationId: $this->anchorSpecializationId,
            headings: $this->headings,
            copyText: $this->copyText,
            membership: $this->membership,
            declaredCourseCount: $this->declaredCourseCount,
            declaredContentUnitCount: $this->declaredContentUnitCount,
        );
    }

    public static function findCourse(string $slug): Course
    {
        return Course::where('slug', $slug)->sole();
    }

    public static function specializationId(string $slug): int
    {
        return Specialization::where('slug', $slug)->sole()->id;
    }
}
