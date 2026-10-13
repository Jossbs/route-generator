<?php

declare(strict_types=1);

namespace App\Enums;

enum RuleSeverity: string
{
    case Blocking = 'blocking';
    case Warning = 'warning';
}
