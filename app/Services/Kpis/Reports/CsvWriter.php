<?php

declare(strict_types=1);

namespace App\Services\Kpis\Reports;

use RuntimeException;

/** CSV para Excel: acentos, escapes estándar y texto que no ejecuta fórmulas. */
final class CsvWriter
{
    /** @return resource */
    public static function open()
    {
        $stream = fopen('php://output', 'w');

        if ($stream === false) {
            throw new RuntimeException('No se pudo abrir la descarga CSV.');
        }

        fwrite($stream, "\xEF\xBB\xBF");

        return $stream;
    }

    /**
     * @param  resource  $stream
     * @param  list<mixed>  $cells
     */
    public static function row($stream, array $cells): void
    {
        $cells = array_map(function (mixed $value): mixed {
            if (is_string($value) && ! is_numeric($value)
                && preg_match('/^(?:\s*[=+@-]|[\t\r\n])/u', $value) === 1) {
                return "'".$value;
            }

            return $value;
        }, $cells);

        if (fputcsv($stream, $cells, escape: '') === false) {
            throw new RuntimeException('No se pudo escribir la descarga CSV.');
        }
    }
}
