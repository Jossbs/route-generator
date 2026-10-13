<?php

declare(strict_types=1);

namespace App\Enums;

enum RouteStatus: string
{
    case Draft = 'draft';
    case Validated = 'validated';
    case Rejected = 'rejected';
    case Rendering = 'rendering';
    case Rendered = 'rendered';
    case Failed = 'failed';

    /**
     * Cómo se lee en la pantalla del asesor.
     *
     * Vive en el enum y no en cada vista porque lo leen la ficha y la bandeja, y
     * dos traducciones del mismo estado se separan en cuanto alguien toca una:
     * «Rechazada» aquí y «Detenida» allá describen lo mismo y suenan a cosas
     * distintas.
     *
     * «Detenida» es deliberado: lo que pasó no es que el documento saliera mal,
     * es que el validador no lo dejó salir.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Validated => 'Validada',
            self::Rejected => 'Detenida',
            self::Rendering => 'Generando',
            self::Rendered => 'Lista',
            self::Failed => 'Falló',
        };
    }
}
