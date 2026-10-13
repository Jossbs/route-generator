<?php

declare(strict_types=1);

namespace App\Services\Catalog;

/**
 * Traduce la rúbrica de `nivel_complejidad` del Diccionario de Datos v1.0 §3.
 *
 * Cuatro criterios de 1 a 4. Los dos primeros se derivan de horas y lecciones
 * publicadas; los dos últimos son juicio metodológico y llegan sembrados. El
 * índice es el promedio simple y el nivel su redondeo, con el empate hacia arriba.
 *
 * Vive como servicio y no como columna transcrita porque los umbrales son
 * revisables: mover uno debe recalcular los 64 cursos corriendo el seeder, no
 * editar 64 filas a mano. `CatalogAnalysisSeederTest` compara los 64 resultados
 * contra `cursos.json`, la corrida de referencia de `calc.py`.
 *
 * | Criterio                         | 1     | 2         | 3         | 4      |
 * |----------------------------------|-------|-----------|-----------|--------|
 * | C1 · Carga formativa (horas)     | ≤ 8   | 9–16      | 17–24     | ≥ 25   |
 * | C2 · Profundidad (horas/lección) | < 1.2 | 1.2–1.79  | 1.8–2.4   | > 2.4  |
 * | C3 · Prerrequisito técnico       | juicio metodológico sembrado       |
 * | C4 · Alcance de aplicación       | juicio metodológico sembrado       |
 *
 * Advertencia de recalibración anotada en el Diccionario: C2 usa horas/lecciones
 * porque `temas` solo existe en 1 de los 64 cursos. Cuando llegue el export del
 * LMS, C2 se recalcula sobre temas y los otros tres criterios no se mueven.
 */
final class ComplexityRubric
{
    /**
     * Cotas de C1 en horas, como {cota, ¿inclusiva?, criterio}. Por encima de la
     * última, el criterio es 4.
     *
     * @var list<array{int, bool, int}>
     */
    private const TRAINING_LOAD_THRESHOLDS = [
        [8, true, 1],
        [16, true, 2],
        [24, true, 3],
    ];

    /**
     * Cotas de C2 en horas por lección. La banda 3 cierra en 2.4 inclusive: es el
     * corte que separa a Yellow Belt (2.4) de Green Belt (2.86).
     *
     * @var list<array{float, bool, int}>
     */
    private const LESSON_DEPTH_THRESHOLDS = [
        [1.2, false, 1],
        [1.8, false, 2],
        [2.4, true, 3],
    ];

    public const MIN_LEVEL = 1;

    public const MAX_LEVEL = 4;

    /**
     * Devuelve null cuando el sitio no publica horas o lecciones. Es deliberado:
     * la regla dura del Diccionario prohíbe rellenar un campo pendiente con un
     * valor plausible, y un nivel inventado ordenaría mal un itinerario.
     */
    public function score(
        ?int $durationHours,
        ?int $lessonsCount,
        int $technicalPrerequisite,
        int $applicationScope,
    ): ?ComplexityScore {
        if ($durationHours === null || $lessonsCount === null || $lessonsCount < 1 || $durationHours < 1) {
            return null;
        }

        $trainingLoad = $this->trainingLoad($durationHours);
        $lessonDepth = $this->lessonDepth($durationHours, $lessonsCount);

        $index = ($trainingLoad + $lessonDepth + $technicalPrerequisite + $applicationScope) / 4;

        return new ComplexityScore(
            trainingLoad: $trainingLoad,
            lessonDepth: $lessonDepth,
            technicalPrerequisite: $technicalPrerequisite,
            applicationScope: $applicationScope,
            index: round($index, 2),
            level: $this->level($index),
        );
    }

    /** C1 · carga formativa, sobre las horas oficiales que sustentan la DC-3. */
    private function trainingLoad(int $hours): int
    {
        return $this->band($hours, self::TRAINING_LOAD_THRESHOLDS);
    }

    /**
     * C2 · profundidad por lección. Sin este criterio, Black Belt (10 lecciones)
     * parecería más ligero que AutoCAD Completo (18); con él marca 4.0 h/lección,
     * la densidad más alta del catálogo, contra 2.0 de AutoCAD.
     */
    private function lessonDepth(int $hours, int $lessons): int
    {
        return $this->band($hours / $lessons, self::LESSON_DEPTH_THRESHOLDS);
    }

    /**
     * La primera banda cuya cota contiene al valor; si ninguna lo hace, el máximo.
     *
     * @param  list<array{int|float, bool, int}>  $thresholds
     */
    private function band(int|float $value, array $thresholds): int
    {
        foreach ($thresholds as [$ceiling, $inclusive, $criterion]) {
            if ($inclusive ? $value <= $ceiling : $value < $ceiling) {
                return $criterion;
            }
        }

        return self::MAX_LEVEL;
    }

    /** Redondeo al entero más cercano, con el empate hacia arriba. */
    private function level(float $index): int
    {
        return (int) floor($index + 0.5);
    }
}
