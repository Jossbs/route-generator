<?php

declare(strict_types=1);

namespace App\Enums\Kpis;

/**
 * El papel de una persona dentro de ventas durante un tramo de tiempo.
 *
 * Vive en `sales_assignments` con fechas, no en `users`: la misma persona puede
 * pasar de auxiliar a asesor o de un equipo a otro, y la venta de marzo tiene que
 * seguir contando para quien era en marzo.
 *
 * El crédito de una venta es siempre del asesor que la cierra (decidido el
 * 2026-09-26). Los demás papeles existen para leer los números por equipo y para
 * las métricas por rol que lleguen después, no para repartir el evento.
 */
enum SalesRole: string
{
    case Asesor = 'asesor';
    case Auxiliar = 'auxiliar';
    case Lider = 'lider';
    case Direccion = 'direccion';

    public function label(): string
    {
        return match ($this) {
            self::Asesor => 'Asesor',
            self::Auxiliar => 'Auxiliar',
            self::Lider => 'Líder',
            self::Direccion => 'Dirección',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $rol): array => ['value' => $rol->value, 'label' => $rol->label()], self::cases());
    }
}
