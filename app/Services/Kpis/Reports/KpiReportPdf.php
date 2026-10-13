<?php

declare(strict_types=1);

namespace App\Services\Kpis\Reports;

use App\Services\Documents\DocumentHtml;
use App\Services\Documents\GotenbergRenderer;
use App\Services\Kpis\Period;
use App\Services\Kpis\Scope;
use App\Services\Kpis\Scoreboard;
use Illuminate\Contracts\View\View;

/**
 * El reporte de un periodo en PDF, para compartirlo en una junta o por correo.
 *
 * Pasa por el mismo Gotenberg que las rutas —`convert()` es público para esto— y
 * no por la cola: es un par de hojas y quien lo pide se queda esperando la
 * descarga, igual que con el catálogo.
 */
final class KpiReportPdf
{
    public function __construct(
        private readonly Scoreboard $scoreboard,
        private readonly GotenbergRenderer $gotenberg,
        private readonly DocumentHtml $html,
    ) {}

    public function view(Period $period, Scope $scope, string $scopeLabel): View
    {
        return view('kpis.reporte', [
            'periodo' => $period,
            'alcance' => $scopeLabel,
            'kpis' => $this->scoreboard->build($period, $scope, detail: false),
            'generado' => now((string) config('kpis.timezone'))->settings(['locale' => 'es'])->translatedFormat('j \d\e F \d\e Y, H:i'),
        ]);
    }

    public function pdf(Period $period, Scope $scope, string $scopeLabel): string
    {
        // Las fuentes y el logo van incrustados, igual que en las rutas.
        return $this->gotenberg->convert($this->html->inline($this->view($period, $scope, $scopeLabel)->render()));
    }
}
