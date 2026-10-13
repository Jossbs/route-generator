<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Membership;
use App\Services\Kpis\SalesEventRecorder;
use App\Services\Kpis\Sources\RouteMembershipSource;

/**
 * Cada membresía que registra el generador de rutas se sugiere como venta al
 * módulo de KPIs. Ver `RouteMembershipSource`.
 *
 * Corre dentro de la misma transacción que emite la ruta: si la emisión se
 * deshace, la sugerencia también.
 */
class MembershipSalesObserver
{
    public function __construct(
        private readonly RouteMembershipSource $source,
        private readonly SalesEventRecorder $recorder,
    ) {}

    public function created(Membership $membership): void
    {
        $this->suggest($membership);
    }

    /** Una corrección de la ruta final puede cambiar el plan o el precio. */
    public function updated(Membership $membership): void
    {
        $this->suggest($membership);
    }

    private function suggest(Membership $membership): void
    {
        if ($membership->created_by === null) {
            return;
        }

        $this->recorder->suggest($this->source->toEvent($membership));
    }
}
