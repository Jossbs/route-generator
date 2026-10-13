<?php

declare(strict_types=1);

namespace App\Services\RouteValidation\Data;

/**
 * Un encabezado del documento, con las especializaciones que quedarían debajo.
 * Es lo que NO_HEADING_MERGE inspecciona: dos especializaciones pueden compartir
 * un mes, jamás un encabezado.
 */
final readonly class HeadingDraft
{
    /** @param list<int> $specializationIds */
    public function __construct(
        public string $key,
        public string $label,
        public array $specializationIds,
        public ?int $month = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'specialization_ids' => $this->specializationIds,
            'month' => $this->month,
        ];
    }
}
