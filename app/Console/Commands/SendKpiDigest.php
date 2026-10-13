<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Kpis\DigestKind;
use App\Services\Kpis\Digests\DigestSender;
use App\Services\Kpis\Period;
use Illuminate\Console\Command;

/**
 * El resumen diario (del día de hoy) o el semanal (de la semana que acaba de
 * terminar) al grupo de ventas en Telegram. Lo programa `routes/console.php`.
 */
class SendKpiDigest extends Command
{
    protected $signature = 'kpis:resumen {tipo : diario o semanal}';

    protected $description = 'Arma el resumen de KPIs, lo guarda y lo manda al grupo de Telegram';

    public function handle(DigestSender $sender): int
    {
        $tipo = DigestKind::tryFrom((string) $this->argument('tipo'));

        if ($tipo === null) {
            $this->error('El tipo es «diario» o «semanal».');

            return self::INVALID;
        }

        $hoy = Period::today();
        $resumen = $sender->send($tipo, $tipo === DigestKind::Diario ? $hoy : $hoy->subWeek());

        if ($resumen->sent_at === null) {
            $this->warn('Se guardó, pero no se envió: '.$resumen->error);

            return self::FAILURE;
        }

        $this->info('Resumen enviado.');

        return self::SUCCESS;
    }
}
