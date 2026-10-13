<?php

declare(strict_types=1);

namespace App\Enums;

enum UsageEventType: string
{
    case Generada = 'generada';
    case Rechazada = 'rechazada';
    case Renderizada = 'renderizada';
    case AbiertaPorLead = 'abierta_por_lead';
}
