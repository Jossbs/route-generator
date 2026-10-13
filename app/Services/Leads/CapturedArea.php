<?php

declare(strict_types=1);

namespace App\Services\Leads;

use App\Enums\LeadArea;

/**
 * El área del lead ya resuelta: o es una de la lista, o es texto estandarizado.
 *
 * Las dos formas no compiten. `area` es el valor tipificado —el que se cuenta, se
 * segmenta y pesa en la composición—; `other` es lo que el asesor escribió cuando
 * ninguna de las catorce le quedaba, guardado de una sola manera para que
 * «CONTROL DE GESTION», «control de gestión» y «Control De Gestion» sean una
 * fila y no tres.
 *
 * Nunca vienen las dos llenas: si el texto libre se reconoció, sube a `area` y
 * `other` queda en null.
 */
final readonly class CapturedArea
{
    public function __construct(
        public ?LeadArea $area = null,
        public ?string $other = null,
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    /** Como se lee en pantalla y en el documento. */
    public function label(): ?string
    {
        return $this->area?->label() ?? $this->other;
    }

    /** @return array{area: string|null, area_other: string|null} */
    public function toAttributes(): array
    {
        return [
            'area' => $this->area?->value,
            'area_other' => $this->other,
        ];
    }
}
