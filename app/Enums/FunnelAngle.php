<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * El gancho comercial con el que se vende el paquete.
 *
 * Los cuatro valores salen de la rotación real de creativos observada en la cuenta
 * publicitaria: el mismo producto rota bajo ángulos distintos en lotes de 6 a 12
 * creativos cada una o dos semanas. Por eso el ángulo es columna aparte de la
 * lista de cursos: un mismo paquete se vende con varios ganchos sin duplicar el
 * embudo. No agregar valores sin evidencia de la cuenta.
 */
enum FunnelAngle: string
{
    case Descuento = 'descuento';
    case Urgencia = 'urgencia';
    case Informativo = 'informativo';
    case Retencion = 'retencion';

    public function label(): string
    {
        return match ($this) {
            self::Descuento => 'Descuento',
            self::Urgencia => 'Urgencia',
            self::Informativo => 'Informativo',
            self::Retencion => 'Retención',
        };
    }
}
