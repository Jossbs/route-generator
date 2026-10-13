<?php

declare(strict_types=1);

namespace App\Services\RouteValidation\Exceptions;

use RuntimeException;

/**
 * Se lanza a propósito en vez de omitir la regla: saltarse en silencio una
 * restricción bloqueante es peor que fallar ruidosamente.
 */
final class UnknownRuleTypeException extends RuntimeException
{
    public static function for(string $ruleCode, string $type): self
    {
        return new self("La regla [{$ruleCode}] declara el tipo [{$type}], que no tiene intérprete registrado.");
    }
}
