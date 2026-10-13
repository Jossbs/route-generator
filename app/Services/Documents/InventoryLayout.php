<?php

declare(strict_types=1);

namespace App\Services\Documents;

/**
 * El inventario del catálogo en hojas de tres columnas; la ruta final puede pedir
 * dos para conservar la composición de su plantilla de referencia.
 *
 * Lo pintan dos documentos: la hoja «Tu membresía» del anual y el catálogo que el
 * asesor descarga para cualquier lead. Los dos cuelgan cada nivel absorbido de su
 * paquete «Completo» (`MODULE_DOUBLE_COUNT` leída en el papel) y los dos cortan
 * las hojas aquí, no en el navegador: una hoja que Chromium inventa sale sin
 * encabezado ni folio.
 *
 * Las medidas son del render real en Carta. Si se toca el ritmo vertical de
 * `_estilos`, se vuelven a medir.
 */
final class InventoryLayout
{
    /** El inventario va en tres columnas balanceadas por el navegador. */
    private const COLUMNAS = 3;

    private const ALTO_HOJA = 850.0;

    /**
     * Lo que la primera hoja del inventario pierde por su título y su entradilla,
     * que es más larga que las demás porque explica la contención de los paquetes.
     */
    private const ALTO_PRIMERA = 700.0;

    private const ALTO_TITULO = 31.0;

    private const ALTO_RENGLON = 15.1;

    private const ALTO_MARGEN = 21.0;

    private const CARACTERES_POR_RENGLON = 30;

    /**
     * Un grupo no se puede partir entre columnas, así que el balanceo del navegador
     * nunca deja las tres exactamente iguales. Se reserva ese margen.
     */
    private const BALANCEO_COLUMNAS = 0.93;

    /** Medidas del inventario de la ruta final, cuyo CSS usa dos columnas. */
    private const ALTO_FINAL_PRIMERA = 770.0;

    private const ALTO_FINAL_SIGUIENTE = 900.0;

    private const ALTO_FINAL_RENGLON = 30.0;

    private const ALTO_FINAL_MARGEN = 18.0;

    /**
     * Cuelga cada nivel absorbido de su paquete «Completo».
     *
     * Solo se hace en el inventario. El itinerario lleva numeración correlativa y
     * su orden es el que propuso el compositor; reordenarlo dejaría los números
     * salteados.
     *
     * @param  list<array<string, mixed>>  $grupos
     * @return list<array<string, mixed>>
     */
    public function nest(array $grupos): array
    {
        return array_map(function (array $grupo): array {
            $absorbidos = [];
            $sueltos = [];

            foreach ($grupo['cursos'] as $curso) {
                if ($curso['horas_absorbidas'] ?? false) {
                    $absorbidos[$curso['unidad'] ?? '-'][] = $curso;
                } else {
                    $sueltos[] = $curso;
                }
            }

            foreach ($absorbidos as &$hijos) {
                usort($hijos, fn (array $a, array $b): int => $this->levelRank($a) <=> $this->levelRank($b));
            }

            unset($hijos);

            $ordenados = [];

            foreach ($sueltos as $curso) {
                $ordenados[] = $curso;
                $unidad = $curso['unidad'] ?? null;

                if ($unidad !== null && isset($absorbidos[$unidad])) {
                    $ordenados = [...$ordenados, ...$absorbidos[$unidad]];
                    unset($absorbidos[$unidad]);
                }
            }

            // Un nivel cuyo paquete no está en esta capa se queda donde estaba.
            foreach ($absorbidos as $huerfanos) {
                $ordenados = [...$ordenados, ...$huerfanos];
            }

            return [...$grupo, 'cursos' => $ordenados];
        }, $grupos);
    }

    /**
     * Reparte el inventario en hojas de tres columnas.
     *
     * Se reparte por alto total y no por número de grupos: una especialización de
     * dieciséis cursos ocupa lo que tres de cuatro, y contarlas por igual llenaba
     * unas hojas y dejaba otras a medias.
     *
     * @param  list<array<string, mixed>>  $grupos
     * @param  int  $columnas  Tres para el catálogo; dos para la ruta final.
     * @return list<list<array<string, mixed>>>
     */
    public function paginate(array $grupos, int $columnas = self::COLUMNAS): array
    {
        if ($grupos === []) {
            return [];
        }

        $esRutaFinal = $columnas === 2;
        $hojas = [];
        $actual = [];
        $usado = 0.0;
        $tope = ($esRutaFinal ? self::ALTO_FINAL_PRIMERA : self::ALTO_PRIMERA)
            * $columnas * self::BALANCEO_COLUMNAS;

        foreach ($grupos as $grupo) {
            $alto = $this->groupHeight($grupo, $esRutaFinal);

            if ($actual !== [] && $usado + $alto > $tope) {
                $hojas[] = $actual;
                $actual = [];
                $usado = 0.0;
                $tope = ($esRutaFinal ? self::ALTO_FINAL_SIGUIENTE : self::ALTO_HOJA)
                    * $columnas * self::BALANCEO_COLUMNAS;
            }

            $actual[] = $grupo;
            $usado += $alto;
        }

        $hojas[] = $actual;

        return $hojas;
    }

    /**
     * El orden pedagógico de los niveles. Básico nunca va después de Intermedio o
     * Avanzado, ni siquiera colgando de un paquete: es `LEVEL_SEQUENCE` leída en el
     * papel.
     *
     * @param  array<string, mixed>  $curso
     */
    private function levelRank(array $curso): int
    {
        return match ($curso['nivel'] ?? null) {
            'basico' => 1,
            'intermedio' => 2,
            'avanzado' => 3,
            default => 4,
        };
    }

    /** @param array<string, mixed> $grupo */
    private function groupHeight(array $grupo, bool $esRutaFinal = false): float
    {
        $alto = $esRutaFinal
            ? self::ALTO_TITULO + self::ALTO_FINAL_MARGEN
            : self::ALTO_TITULO + self::ALTO_MARGEN;
        $altoRenglon = $esRutaFinal ? self::ALTO_FINAL_RENGLON : self::ALTO_RENGLON;

        foreach ($grupo['cursos'] as $curso) {
            $alto += $this->wrappedLines((string) $curso['nombre']) * $altoRenglon;
        }

        return $alto;
    }

    /** En cuántos renglones cae un nombre dentro de una columna. */
    private function wrappedLines(string $texto): int
    {
        return max(1, (int) ceil(mb_strlen($texto) / self::CARACTERES_POR_RENGLON));
    }
}
