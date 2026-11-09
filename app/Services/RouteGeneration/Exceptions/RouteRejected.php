<?php

declare(strict_types=1);

namespace App\Services\RouteGeneration\Exceptions;

use App\Services\RouteValidation\Data\ValidationReport;
use RuntimeException;

/** Devuelve el borrador al editor antes de modificar un documento existente. */
final class RouteRejected extends RuntimeException
{
    public function __construct(public readonly ValidationReport $report)
    {
        parent::__construct(implode(' ', $report->blockingMessages()));
    }
}
