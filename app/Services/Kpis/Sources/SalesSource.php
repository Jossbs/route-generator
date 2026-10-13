<?php

declare(strict_types=1);

namespace App\Services\Kpis\Sources;

use App\Enums\Kpis\SalesEventSource;
use Carbon\CarbonImmutable;

/**
 * De dónde pueden llegar eventos que nadie capturó a mano.
 *
 * Es la misma costura que `LeadSource` y `CurrentAdvisor` (docs/ARQUITECTURA.md §3): una
 * interfaz con varias implementaciones, de las que hoy se escribe solo la que ya
 * tiene datos —las membresías del generador de rutas—. Kommo y el CRM propio de
 * SyS serán otras dos, y lo único que tendrán que hacer es traducir lo suyo a
 * `SuggestedEvent`; el resto —la llave contra duplicados, la confirmación, el
 * cierre de mes— ya lo resuelve `SalesEventRecorder::suggest()`.
 *
 * No se escriben todavía: inventar hoy el contrato de la API de Kommo sería
 * adivinar (decidido el 2026-09-26, igual que con los leads).
 */
interface SalesSource
{
    public function source(): SalesEventSource;

    /**
     * Lo que la fuente tiene desde esa fecha.
     *
     * @return iterable<SuggestedEvent>
     */
    public function since(CarbonImmutable $since): iterable;
}
