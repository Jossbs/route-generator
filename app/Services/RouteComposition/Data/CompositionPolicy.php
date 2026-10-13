<?php

declare(strict_types=1);

namespace App\Services\RouteComposition\Data;

use App\Models\DocumentType;

/**
 * Lectura tipada de `document_types.composition_policy`.
 *
 * La forma de cada documento es dato, no código: cuántas complementarias lleva,
 * cuántos cursos entran en cada una, en cuántos meses se reparte el itinerario.
 * Un tercer documento —renovación, reactivación— se afina cambiando este JSONB.
 */
final readonly class CompositionPolicy
{
    /** @param array<string, mixed> $values */
    private function __construct(private array $values) {}

    public static function for(DocumentType $documentType): self
    {
        return new self($documentType->composition_policy ?? []);
    }

    public function complementaryCount(): int
    {
        return $this->int('complementarias', 2);
    }

    public function coursesPerComplementary(): int
    {
        return $this->int('cursos_por_complementaria.max', 3);
    }

    public function minCoursesPerComplementary(): int
    {
        return $this->int('cursos_por_complementaria.min', 2);
    }

    public function minPrograms(): int
    {
        return $this->int('programas_esperados.min', 8);
    }

    public function maxPrograms(): int
    {
        return $this->int('programas_esperados.max', 14);
    }

    public function months(): int
    {
        return $this->int('meses', 12);
    }

    /** La especialización que el lead pidió abre el mes 1, para que sienta continuidad. */
    public function anchorOpensFirstMonth(): bool
    {
        return ($this->values['mes_1'] ?? null) === 'especializacion_ancla';
    }

    /**
     * Cuánto pesa la afinidad al arquetipo frente a la complejidad del curso al
     * ordenar candidatos. Mientras los ocho arquetipos del Excel no lleguen, la
     * afinidad casi siempre es cero y manda el desempate por complejidad.
     */
    public function affinityWeight(): float
    {
        return $this->float('peso_afinidad', 0.7);
    }

    public function complexityWeight(): float
    {
        return $this->float('peso_complejidad', 0.3);
    }

    private function int(string $key, int $default): int
    {
        $value = data_get($this->values, $key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    private function float(string $key, float $default): float
    {
        $value = data_get($this->values, $key, $default);

        return is_numeric($value) ? (float) $value : $default;
    }
}
