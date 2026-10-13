<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kpis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Kpis\Concerns\RespondsWithToast;
use App\Models\Kpi;
use App\Services\Kpis\Settings\KpiDefinitionWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KpiDefinitionController extends Controller
{
    use RespondsWithToast;

    public function store(Request $request, KpiDefinitionWriter $writer): RedirectResponse
    {
        $kpi = $writer->save(null, $request->all());

        return $this->backWithToast("Se creó el KPI «{$kpi->name}».");
    }

    public function update(Request $request, Kpi $kpi, KpiDefinitionWriter $writer): RedirectResponse
    {
        $writer->save($kpi, $request->all());

        return $this->backWithToast('KPI guardado.');
    }
}
