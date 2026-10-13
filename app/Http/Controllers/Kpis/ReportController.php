<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kpis;

use App\Http\Controllers\Controller;
use App\Services\Kpis\Period;
use App\Services\Kpis\Reports\KpiReportPdf;
use App\Services\Kpis\Reports\SummaryCsv;
use App\Services\Kpis\SalesRoster;
use App\Services\Kpis\Scope;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Las descargas del tablero: el resumen en CSV y el reporte en PDF, con el mismo
 * periodo y alcance que se están viendo.
 */
class ReportController extends Controller
{
    public function csv(Request $request, SummaryCsv $csv, SalesRoster $roster): StreamedResponse
    {
        $alcance = Scope::fromInput($request->query('alcance'));

        return $csv->download(
            Period::fromInput($request->query('periodo'), $request->query('fecha')),
            $alcance,
            $roster->scopeLabel($alcance),
        );
    }

    public function pdf(Request $request, KpiReportPdf $reporte, SalesRoster $roster): Response
    {
        $periodo = Period::fromInput($request->query('periodo'), $request->query('fecha'));
        $alcance = Scope::fromInput($request->query('alcance'));
        $nombre = 'KPIs '.$periodo->start->toDateString().' '.$roster->scopeLabel($alcance).'.pdf';

        return response($reporte->pdf($periodo, $alcance, $roster->scopeLabel($alcance)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.addcslashes($nombre, '"\\').'"',
        ]);
    }
}
