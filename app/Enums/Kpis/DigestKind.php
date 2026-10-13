<?php

declare(strict_types=1);

namespace App\Enums\Kpis;

enum DigestKind: string
{
    case Diario = 'diario';
    case Semanal = 'semanal';
}
