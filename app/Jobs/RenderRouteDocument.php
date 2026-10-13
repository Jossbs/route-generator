<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\RouteStatus;
use App\Enums\UsageEventType;
use App\Models\LearningRoute;
use App\Services\Documents\DocumentRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * El render corre en cola.
 *
 * Chromium tarda segundos: el asesor sigue trabajando mientras se genera el PDF.
 *
 * Una ruta que no pasó el validador no llega aquí: el documento se renderiza
 * después de que la ruta se aprueba, nunca antes.
 */
class RenderRouteDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    /** @var list<int> */
    public array $backoff = [5, 15];

    public ?string $snapshotHash = null;

    public ?int $revision = null;

    public function __construct(
        public readonly int $routeId,
        ?string $snapshotHash = null,
        ?int $revision = null,
    ) {
        $this->snapshotHash = $snapshotHash;
        $this->revision = $revision;
    }

    public function handle(DocumentRenderer $renderer): void
    {
        DB::transaction(function () use ($renderer): void {
            // Serializa renders duplicados y las correcciones/borrados. El archivo
            // y su estado deben pertenecer al mismo snapshot hasta el commit.
            $route = LearningRoute::query()->lockForUpdate()->find($this->routeId);

            if ($route === null || $route->status !== RouteStatus::Validated
                || ($this->snapshotHash !== null && $route->snapshot_hash !== $this->snapshotHash)
                || ($this->revision !== null && $route->revisions !== $this->revision)) {
                return;
            }

            if (! $route->snapshotIsIntact()) {
                throw new RuntimeException('El snapshot cambió después de validar la ruta.');
            }

            $route->forceFill(['status' => RouteStatus::Rendering])->save();

            $renderer->render($route);

            $route->usageEvents()->create([
                'advisor_id' => $route->advisor_id,
                'event' => UsageEventType::Renderizada,
            ]);
        });
    }

    /**
     * Un render fallido se asienta como tal. La bandeja tiene que poder decir que
     * el documento no salió, en vez de dejarlo «generando» para siempre.
     */
    public function failed(?Throwable $exception): void
    {
        // Un fallo tardío de un trabajo anterior no puede deshacer una corrección
        // ni cambiar el estado de un documento que otro trabajo ya terminó.
        LearningRoute::whereKey($this->routeId)
            ->whereIn('status', [RouteStatus::Validated, RouteStatus::Rendering])
            ->when($this->snapshotHash !== null, fn ($query) => $query->where('snapshot_hash', $this->snapshotHash))
            ->when($this->revision !== null, fn ($query) => $query->where('revisions', $this->revision))
            ->update(['status' => RouteStatus::Failed]);
    }
}
