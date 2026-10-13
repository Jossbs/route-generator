<?php

declare(strict_types=1);

namespace App\Services\Kpis\Reports;

use App\Models\SalesAssignment;
use App\Models\SalesEvent;
use App\Models\SalesEventField;
use App\Models\SalesEventType;
use App\Services\Kpis\Period;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Los eventos crudos de un periodo, para abrirlos en Excel.
 *
 * CSV con BOM y no .xlsx: Excel lo abre con acentos y columnas sin que el módulo
 * cargue una librería de hojas de cálculo (docs/ARQUITECTURA.md §7: justificar cada
 * dependencia, y esta no alcanza a justificarse).
 *
 * Una columna por campo de los tipos incluidos; el equipo es el del día del evento.
 */
final class EventsCsv
{
    public function download(Period $period, ?SalesEventType $type = null): StreamedResponse
    {
        $tipos = $type !== null
            ? collect([$type->load('fields')])
            : SalesEventType::query()->with('fields')->orderBy('name')->get();

        /** @var array<string, SalesEventField> $campos */
        $campos = [];

        foreach ($tipos as $t) {
            foreach ($t->fields as $campo) {
                $campos[$campo->key] ??= $campo;
            }
        }

        $nombre = 'eventos-'.$period->start->toDateString().'-a-'.$period->end->toDateString().'.csv';

        return response()->streamDownload(function () use ($period, $tipos, $campos): void {
            $salida = CsvWriter::open();

            CsvWriter::row($salida, [
                'Fecha', 'Tipo', 'Asesor', 'Equipo', 'Estado', 'Anulado', 'Fuente',
                ...array_map(fn (SalesEventField $c): string => $c->label, array_values($campos)),
                'Notas', 'Ajuste', 'Motivo del ajuste',
            ]);

            $tramos = SalesAssignment::query()
                ->with('team:id,name')
                ->overlapping($period->start->toDateString(), $period->end->toDateString())
                ->get();

            SalesEvent::query()
                ->with(['type:id,name', 'advisor:id,name'])
                ->whereIn('sales_event_type_id', $tipos->pluck('id'))
                ->whereBetween('occurred_on', [$period->start->toDateString(), $period->end->toDateString()])
                ->orderBy('occurred_on')
                ->orderBy('id')
                ->lazy()
                ->each(function (SalesEvent $evento) use ($salida, $campos, $tramos): void {
                    $dia = $evento->occurred_on->toDateString();
                    $tramo = $tramos->first(fn (SalesAssignment $a): bool => $a->user_id === $evento->user_id
                        && $a->starts_on->toDateString() <= $dia
                        && ($a->ends_on === null || $a->ends_on->toDateString() >= $dia));

                    CsvWriter::row($salida, [
                        $dia,
                        $evento->type->name,
                        $evento->advisor->name,
                        $tramo?->team->name ?? '',
                        $evento->status->label(),
                        $evento->isVoided() ? 'Sí' : 'No',
                        $evento->source->label(),
                        ...array_map(fn (SalesEventField $c): string => $this->cell($c, $evento->values[$c->key] ?? null), array_values($campos)),
                        $evento->notes ?? '',
                        $evento->is_adjustment ? 'Sí' : 'No',
                        $evento->adjustment_reason ?? '',
                    ]);
                });

            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function cell(SalesEventField $campo, mixed $valor): string
    {
        return match (true) {
            $valor === null => '',
            is_bool($valor) => $valor ? 'Sí' : 'No',
            $campo->options !== [] => $campo->optionLabel($valor),
            default => (string) $valor,
        };
    }
}
