<?php

declare(strict_types=1);

namespace App\Services\RouteValidation\Data;

use App\Enums\RouteLayer;

/**
 * La ruta que propone el generador. El validador la aprueba o la rechaza; solo
 * entonces se renderiza. Una ruta inválida nunca debe llegar al PDF.
 */
final readonly class RouteDraft
{
    /**
     * @param  list<RouteItemDraft>  $items
     * @param  list<HeadingDraft>  $headings  Encabezados tal como se imprimirán.
     * @param  list<string>  $copyText  Bloques de copy ya ensamblados, en el orden del documento.
     * @param  array<string, mixed>|null  $membership  Datos de membresía, si el documento los exige.
     * @param  RuleWaivers|null  $waivers  Lo que el embudo de origen decidió ignorar.
     */
    public function __construct(
        public string $documentTypeSlug,
        public array $items,
        public ?int $anchorSpecializationId = null,
        public array $headings = [],
        public array $copyText = [],
        public ?array $membership = null,
        public ?int $declaredCourseCount = null,
        public ?int $declaredContentUnitCount = null,
        public ?RuleWaivers $waivers = null,
    ) {}

    /**
     * @param  list<string>|null  $layers  Valores de RouteLayer; null = todas.
     * @return list<RouteItemDraft>
     */
    public function itemsInLayers(?array $layers): array
    {
        if ($layers === null) {
            return $this->items;
        }

        return array_values(array_filter(
            $this->items,
            fn (RouteItemDraft $item): bool => in_array($item->layer->value, $layers, true),
        ));
    }

    /** @return list<RouteItemDraft> */
    public function itemsInLayer(RouteLayer $layer): array
    {
        return $this->itemsInLayers([$layer->value]);
    }

    /** Todo el texto que se imprimirá, concatenado para escaneo. */
    public function assembledText(): string
    {
        return trim(implode("\n", array_filter($this->copyText, fn (?string $b): bool => filled($b))));
    }

    /** @return list<int> */
    public function courseIds(): array
    {
        return array_values(array_unique(array_map(
            fn (RouteItemDraft $item): int => $item->courseId,
            $this->items,
        )));
    }

    /** @return list<int> */
    public function specializationIds(): array
    {
        $ids = array_map(fn (RouteItemDraft $item): int => $item->specializationId, $this->items);

        foreach ($this->headings as $heading) {
            $ids = [...$ids, ...$heading->specializationIds];
        }

        if ($this->anchorSpecializationId !== null) {
            $ids[] = $this->anchorSpecializationId;
        }

        return array_values(array_unique($ids));
    }
}
