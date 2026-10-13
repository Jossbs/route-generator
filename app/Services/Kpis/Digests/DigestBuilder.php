<?php

declare(strict_types=1);

namespace App\Services\Kpis\Digests;

use App\Enums\Kpis\DigestKind;
use App\Enums\Kpis\KpiPeriodType;
use App\Enums\Kpis\MeasureUnit;
use App\Services\Kpis\Period;
use App\Services\Kpis\Scope;
use App\Services\Kpis\Scoreboard;
use App\Services\Kpis\ValueFormatter;
use Carbon\CarbonImmutable;

/**
 * El texto del resumen diario y del semanal, en el HTML reducido de Telegram.
 *
 * Va al grupo, así que lo lee todo el equipo: celebra a quien destacó y muestra el
 * ritmo del equipo, sin exhibir a quien va atrás (decidido el 2026-09-26). El
 * ranking completo está en el tablero.
 */
final class DigestBuilder
{
    /** Cuántos asesores se destacan por medida. */
    private const HIGHLIGHTS = 3;

    public function __construct(private readonly Scoreboard $scoreboard) {}

    /** @return array{period: Period, body: string} */
    public function build(DigestKind $kind, CarbonImmutable $date): array
    {
        $periodo = Period::containing($kind === DigestKind::Diario ? KpiPeriodType::Dia : KpiPeriodType::Semana, $date);
        $mes = Period::containing(KpiPeriodType::Mes, $periodo->end);
        // El avance del mes se mide al último día del resumen, no a hoy: el
        // semanal del lunes habla de la semana que cerró el domingo.
        $corte = $periodo->end;

        $delPeriodo = $this->scoreboard->build($periodo, Scope::general(), $corte);
        $delMes = collect($this->scoreboard->build($mes, Scope::general(), $corte, detail: false))->keyBy('id');

        $titulo = $kind === DigestKind::Diario ? 'Resumen del día' : 'Resumen de la semana';
        $lineas = ['<b>📊 '.$titulo.'</b>', '<i>'.e(ucfirst($periodo->label())).'</i>', ''];

        if ($delPeriodo === []) {
            $lineas[] = 'Todavía no hay KPIs configurados.';

            return ['period' => $periodo, 'body' => implode("\n", $lineas)];
        }

        foreach ($delPeriodo as $kpi) {
            $lineas[] = '<b>'.e($kpi['name']).'</b>';
            $medidasDelMes = $delMes[$kpi['id']]['measures'] ?? [];
            $mesDelKpi = collect(is_array($medidasDelMes) ? $medidasDelMes : [])->keyBy('id');

            foreach ($kpi['measures'] as $medida) {
                $unidad = MeasureUnit::from($medida['unit']);
                $linea = '• '.e($medida['name']).': <b>'.e($medida['progress']['value_label']).'</b>';

                if ($medida['progress']['target'] !== null) {
                    $linea .= ' de '.e($medida['progress']['target_label']);
                }

                $lineas[] = $linea;

                $acumulado = $mesDelKpi[$medida['id']]['progress'] ?? null;

                if ($acumulado !== null && $acumulado['target'] !== null) {
                    $lineas[] = '   Mes: '.e($acumulado['value_label']).' de '.e($acumulado['target_label'])
                        .' ('.(int) round($acumulado['percent'] ?? 0).' %) '.$this->emoji($acumulado['status']);
                }

                $destacados = array_values(array_filter(
                    $medida['ranking'],
                    fn (array $fila): bool => ($fila['value'] ?? 0) > 0,
                ));

                if ($medida['higher_is_better'] && $destacados !== []) {
                    $top = array_slice($destacados, 0, self::HIGHLIGHTS);
                    $lineas[] = '   🏆 '.implode(' · ', array_map(
                        fn (array $f): string => e($f['name']).' '.e(ValueFormatter::format($f['value'], $unidad))
                            .($f['streak'] >= 3 ? ' 🔥'.$f['streak'] : ''),
                        $top,
                    ));
                }
            }

            $lineas[] = '';
        }

        $lineas[] = $kind === DigestKind::Diario
            ? '<i>Cada venta suma. ¡Vamos por mañana! 💪</i>'
            : '<i>Nueva semana, nueva oportunidad. ¡Vamos con todo! 💪</i>';

        return ['period' => $periodo, 'body' => implode("\n", $lineas)];
    }

    private function emoji(string $status): string
    {
        return match ($status) {
            'logrado' => '✅',
            'en_ritmo' => '🟢',
            'cerca' => '🟡',
            default => '⏳',
        };
    }
}
