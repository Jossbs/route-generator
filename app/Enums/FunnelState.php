<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * El ciclo de vida del embudo.
 *
 * En la v1 solo se estrenan dos casillas: el embudo nace `activo` —se captura
 * para usarse, no hay paso de publicación— y `archivado` lo deja de solo lectura.
 * `borrador` y `pausado` siguen aquí porque el ciclo completo es post-v1 y la
 * columna ya los admite; nada en el módulo los produce todavía.
 *
 * Un embudo `activo` cuya vigencia ya venció no se archiva solo: desaparece del
 * selector del asesor pero conserva su estado, para que el listado interno muestre
 * por qué desapareció.
 */
enum FunnelState: string
{
    case Borrador = 'borrador';
    case Activo = 'activo';
    case Pausado = 'pausado';
    case Archivado = 'archivado';

    /** El archivado es histórico y no se toca; todo lo demás se edita. */
    public function isEditable(): bool
    {
        return $this !== self::Archivado;
    }

    /**
     * ¿Puede el asesor verlo en el selector? La vigencia se comprueba aparte:
     * el estado dice si está publicado, las fechas dicen si sigue vigente.
     */
    public function isSelectable(): bool
    {
        return $this === self::Activo;
    }

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Activo => 'Activo',
            self::Pausado => 'Pausado',
            self::Archivado => 'Archivado',
        };
    }
}
