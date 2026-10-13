<?php

declare(strict_types=1);

namespace App\Enums\Kpis;

/**
 * De dónde llegó un evento.
 *
 * Con `external_id` forma la llave única que impide importar dos veces lo mismo.
 * `kommo` y `crm_sys` existen desde ahora para que traer datos de esos CRM sea
 * escribir una fuente, no migrar la tabla. Ver App\Services\Kpis\Sources\SalesSource.
 */
enum SalesEventSource: string
{
    case Manual = 'manual';
    case Rutas = 'rutas';
    case Kommo = 'kommo';
    case CrmSys = 'crm_sys';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Captura manual',
            self::Rutas => 'Generador de rutas',
            self::Kommo => 'Kommo',
            self::CrmSys => 'CRM SyS',
        };
    }
}
