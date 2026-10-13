<?php

declare(strict_types=1);

namespace App\Services\Catalog;

/**
 * El resultado de aplicar la rúbrica a un curso, con los cuatro criterios a la
 * vista. Se persiste desglosado, no solo el nivel: un lead que pregunta «¿por qué
 * este curso va después de aquél?» merece una respuesta que se pueda rastrear.
 */
final readonly class ComplexityScore
{
    public function __construct(
        public int $trainingLoad,
        public int $lessonDepth,
        public int $technicalPrerequisite,
        public int $applicationScope,
        public float $index,
        public int $level,
    ) {}

    /** @return array{c1: int, c2: int, c3: int, c4: int} */
    public function criteria(): array
    {
        return [
            'c1' => $this->trainingLoad,
            'c2' => $this->lessonDepth,
            'c3' => $this->technicalPrerequisite,
            'c4' => $this->applicationScope,
        ];
    }
}
