<?php

declare(strict_types=1);

namespace App\Services\Kpis\Reports;

use App\Services\Kpis\Period;
use App\Services\Kpis\Scope;
use App\Services\Kpis\Scoreboard;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * El resumen de un periodo por medida y por asesor: valor, meta y avance.
 *
 * Es la tabla que se pega en una presentación o se cruza en Excel, con los mismos
 * números que el tablero porque sale del mismo `Scoreboard`.
 */
final class SummaryCsv
{
    public function __construct(private readonly Scoreboard $scoreboard) {}

    public function download(Period $period, Scope $scope, string $scopeLabel): StreamedResponse
    {
        $kpis = $this->scoreboard->build($period, $scope, detail: false);
        $nombre = 'kpis-'.$period->type->value.'-'.$period->start->toDateString().'.csv';

        return response()->streamDownload(function () use ($kpis, $scopeLabel): void {
            $salida = CsvWriter::open();
            CsvWriter::row($salida, ['KPI', 'Medida', 'Quién', 'Equipo', 'Valor', 'Meta', 'Avance %', 'Estado']);

            foreach ($kpis as $kpi) {
                foreach ($kpi['measures'] as $medida) {
                    $p = $medida['progress'];
                    CsvWriter::row($salida, [$kpi['name'], $medida['name'], $scopeLabel, '', $p['value'], $p['target'], self::percent($p['percent']), $p['status']]);

                    foreach ($medida['ranking'] as $fila) {
                        CsvWriter::row($salida, [$kpi['name'], $medida['name'], $fila['name'], $fila['team'] ?? '', $fila['value'], $fila['target'], self::percent($fila['percent']), $fila['status']]);
                    }
                }
            }

            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function percent(?float $p): string
    {
        return $p === null ? '' : (string) round($p, 1);
    }
}
