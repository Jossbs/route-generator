<?php

declare(strict_types=1);

namespace App\Services\RouteGeneration\Data;

use App\Enums\RouteStatus;
use App\Models\LearningRoute;
use App\Services\RouteValidation\Data\ValidationReport;

/**
 * Lo que devuelve el generador. La ruta siempre queda persistida, apruebe o no:
 * un rechazo del validador es información que el asesor necesita y que la bitácora
 * tiene que conservar.
 */
final readonly class GenerationResult
{
    public function __construct(
        public LearningRoute $route,
        public ValidationReport $report,
    ) {}

    public function approved(): bool
    {
        return $this->route->status === RouteStatus::Validated;
    }

    /**
     * Los motivos del rechazo, redactados para el asesor.
     *
     * @return list<string>
     */
    public function reasons(): array
    {
        return $this->report->blockingMessages();
    }
}
