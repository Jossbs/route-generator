<?php

declare(strict_types=1);

namespace App\Enums;

enum ValidationResult: string
{
    case Pass = 'pass';
    case Fail = 'fail';
    case Skip = 'skip';
}
