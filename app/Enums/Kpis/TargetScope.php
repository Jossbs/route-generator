<?php

declare(strict_types=1);

namespace App\Enums\Kpis;

/**
 * A quién se le fija una meta.
 *
 * Las tres se fijan por separado (decidido el 2026-09-26): la meta del equipo no
 * es la suma de las individuales ni la general la de los equipos. Quien dirige
 * puede pedirle al equipo más —o menos— de lo que suma su gente.
 */
enum TargetScope: string
{
    case General = 'general';
    case Equipo = 'equipo';
    case Persona = 'persona';

    public function label(): string
    {
        return match ($this) {
            self::General => 'Todo ventas',
            self::Equipo => 'Equipo',
            self::Persona => 'Persona',
        };
    }
}
