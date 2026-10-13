<?php

declare(strict_types=1);

namespace App\Services\RouteValidation\Data;

use App\Enums\RouteItemRole;
use App\Enums\RouteLayer;

/**
 * Un renglón de la ruta propuesta, antes de persistirse.
 *
 * Solo lleva identificadores: los atributos del curso (nivel, unidad de contenido)
 * los resuelve el validador contra el catálogo, para que el generador no tenga que
 * duplicarlos ni pueda contradecirlos.
 */
final readonly class RouteItemDraft
{
    public function __construct(
        public int $courseId,
        public int $specializationId,
        public RouteLayer $layer,
        public RouteItemRole $role,
        public int $position,
        public ?int $month = null,
        public bool $countsTowardTotal = true,
        /** Por qué este bloque está en la ruta. La lee el asesor, nunca el lead. */
        public ?string $justification = null,
        /** El mes en que termina, si el curso abarca más de uno a su ritmo. */
        public ?int $monthEnd = null,
    ) {}

    /**
     * Clave de orden dentro del itinerario: primero el mes, luego la posición.
     *
     * @return array{int, int}
     */
    public function sequenceKey(): array
    {
        return [$this->month ?? 0, $this->position];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'course_id' => $this->courseId,
            'specialization_id' => $this->specializationId,
            'layer' => $this->layer->value,
            'role' => $this->role->value,
            'position' => $this->position,
            'month' => $this->month,
            'month_end' => $this->monthEnd,
            'counts_toward_total' => $this->countsTowardTotal,
            'justification' => $this->justification,
        ];
    }
}
