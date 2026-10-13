<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kpis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Kpis\Concerns\RespondsWithToast;
use App\Models\SalesEventType;
use App\Services\Kpis\Settings\EventTypeWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EventTypeController extends Controller
{
    use RespondsWithToast;

    public function store(Request $request, EventTypeWriter $writer): RedirectResponse
    {
        $tipo = $writer->save(null, $request->all());

        return $this->backWithToast("Se creó el tipo «{$tipo->name}».");
    }

    public function update(Request $request, SalesEventType $eventType, EventTypeWriter $writer): RedirectResponse
    {
        $writer->save($eventType, $request->all());

        return $this->backWithToast('Tipo de evento guardado.');
    }
}
