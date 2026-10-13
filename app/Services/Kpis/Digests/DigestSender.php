<?php

declare(strict_types=1);

namespace App\Services\Kpis\Digests;

use App\Enums\Kpis\DigestKind;
use App\Models\KpiDigest;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Arma el resumen, lo guarda y lo manda.
 *
 * Se guarda antes de mandarse y pase lo que pase: el resumen también se lee
 * dentro del módulo, y un envío fallido tiene que verse ahí con su error en vez de
 * perderse en un log.
 */
final class DigestSender
{
    public function __construct(
        private readonly DigestBuilder $builder,
        private readonly TelegramChannel $telegram,
    ) {}

    public function send(DigestKind $kind, CarbonImmutable $date): KpiDigest
    {
        ['period' => $periodo, 'body' => $cuerpo] = $this->builder->build($kind, $date);

        $resumen = KpiDigest::create([
            'kind' => $kind,
            'period_start' => $periodo->start->toDateString(),
            'period_end' => $periodo->end->toDateString(),
            'body' => $cuerpo,
        ]);

        try {
            $this->telegram->send($cuerpo);
            $resumen->update(['sent_at' => now()]);
        } catch (Throwable $e) {
            $resumen->update(['error' => $e->getMessage()]);
        }

        return $resumen;
    }
}
