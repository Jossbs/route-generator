<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kpis;

use App\Enums\Kpis\DigestKind;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Kpis\Concerns\RespondsWithToast;
use App\Models\KpiDigest;
use App\Models\KpiPeriodClosure;
use App\Models\SalesEvent;
use App\Services\Kpis\Digests\DigestSender;
use App\Services\Kpis\Digests\TelegramChannel;
use App\Services\Kpis\Period;
use App\Services\Kpis\PeriodCloser;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cierres de mes y resúmenes: lo que ya se reportó.
 *
 * Los dos viven juntos porque responden a la misma pregunta —qué números se
 * dieron por buenos y a quién se le dijeron— y los dos los administra la misma
 * persona.
 */
class ClosureController extends Controller
{
    use RespondsWithToast;

    public function index(TelegramChannel $telegram): Response
    {
        $hoy = Period::today();
        $cerrados = KpiPeriodClosure::query()->with('closer:id,name')->orderByDesc('period_start')->get();
        $yaCerrados = $cerrados->map(fn (KpiPeriodClosure $c): string => $c->period_start->toDateString())->all();

        // Los meses que ya terminaron y siguen abiertos, desde el primer evento.
        $primero = SalesEvent::query()->min('occurred_on');
        $porCerrar = [];

        if ($primero !== null) {
            for ($mes = CarbonImmutable::parse($primero)->startOfMonth(); $mes->lt($hoy->startOfMonth()); $mes = $mes->addMonth()) {
                if (! in_array($mes->toDateString(), $yaCerrados, true)) {
                    $porCerrar[] = ['value' => $mes->toDateString(), 'label' => ucfirst($mes->settings(['locale' => 'es'])->translatedFormat('F Y'))];
                }
            }
        }

        return Inertia::render('kpis/cierres', [
            'cierres' => $cerrados->map(fn (KpiPeriodClosure $c): array => [
                'id' => $c->id,
                'month' => ucfirst($c->period_start->settings(['locale' => 'es'])->translatedFormat('F Y')),
                'fecha' => $c->period_start->toDateString(),
                'closed_at' => $c->closed_at->toIso8601String(),
                'by' => $c->closer?->name,
                'adjustments' => SalesEvent::query()
                    ->where('is_adjustment', true)
                    ->whereBetween('occurred_on', [$c->period_start->toDateString(), $c->period_start->endOfMonth()->toDateString()])
                    ->count(),
            ]),
            'porCerrar' => array_reverse($porCerrar),
            'resumenes' => KpiDigest::query()->latest('id')->limit(20)->get()->map(fn (KpiDigest $d): array => [
                'id' => $d->id,
                'kind' => $d->kind->value,
                'period_start' => $d->period_start->toDateString(),
                'period_end' => $d->period_end->toDateString(),
                'body' => $d->body,
                'sent_at' => $d->sent_at?->toIso8601String(),
                'error' => $d->error,
                'created_at' => $d->created_at?->toIso8601String(),
            ]),
            'telegramConfigurado' => $telegram->isConfigured(),
        ]);
    }

    public function store(Request $request, PeriodCloser $closer): RedirectResponse
    {
        $datos = $request->validate(['month' => ['required', 'date']], [], ['month' => 'mes']);
        $cierre = $closer->close(CarbonImmutable::parse($datos['month'])->startOfMonth(), $request->user());

        return $this->backWithToast('Se cerró '.$cierre->period_start->settings(['locale' => 'es'])->translatedFormat('F Y').'.');
    }

    /** Manda ahora el resumen de hoy o de la semana pasada, sin esperar al horario. */
    public function digest(Request $request, DigestSender $sender): RedirectResponse
    {
        $datos = $request->validate(['kind' => ['required', Rule::enum(DigestKind::class)]]);
        $tipo = DigestKind::from($datos['kind']);
        $hoy = Period::today();
        $resumen = $sender->send($tipo, $tipo === DigestKind::Diario ? $hoy : $hoy->subWeek());

        return $resumen->sent_at !== null
            ? $this->backWithToast('Resumen enviado al grupo.')
            : $this->backWithToast('El resumen se guardó, pero no se envió: '.$resumen->error, 'error');
    }
}
