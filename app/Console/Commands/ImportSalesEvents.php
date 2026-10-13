<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Kpis\SalesEventRecorder;
use App\Services\Kpis\Sources\RouteMembershipSource;
use App\Services\Kpis\Sources\SalesSource;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Trae lo que una fuente tenga desde una fecha, como eventos por confirmar.
 *
 * Hoy la única fuente es el generador de rutas —útil para sugerir las membresías
 * que se registraron antes de que existiera este módulo—. Kommo y el CRM de SyS
 * se agregan a `sources()` cuando se escriban. Es idempotente: correrlo dos
 * veces no duplica nada.
 */
class ImportSalesEvents extends Command
{
    protected $signature = 'kpis:importar {fuente=rutas} {--desde= : fecha AAAA-MM-DD; por omisión, hace 90 días}';

    protected $description = 'Importa eventos sugeridos desde una fuente (hoy: rutas)';

    public function handle(SalesEventRecorder $recorder): int
    {
        $fuente = $this->sources()[(string) $this->argument('fuente')] ?? null;

        if ($fuente === null) {
            $this->error('Fuentes disponibles: '.implode(', ', array_keys($this->sources())).'.');

            return self::INVALID;
        }

        $desde = filled($this->option('desde'))
            ? CarbonImmutable::parse((string) $this->option('desde'))
            : CarbonImmutable::now()->subDays(90);
        $total = 0;

        foreach ($fuente->since($desde) as $sugerencia) {
            if ($recorder->suggest($sugerencia) !== null) {
                $total++;
            }
        }

        $this->info("{$total} eventos sugeridos o actualizados.");

        return self::SUCCESS;
    }

    /** @return array<string, SalesSource> */
    private function sources(): array
    {
        return ['rutas' => app(RouteMembershipSource::class)];
    }
}
