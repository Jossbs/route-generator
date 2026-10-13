<?php

declare(strict_types=1);

namespace App\Services\Kpis\Sources;

use App\Enums\Kpis\SalesEventSource;
use Carbon\CarbonImmutable;

/**
 * Un evento que una fuente propone. No cuenta hasta que alguien lo confirma.
 */
final class SuggestedEvent
{
    /** @param  array<string, mixed>  $values */
    public function __construct(
        public readonly SalesEventSource $source,
        public readonly string $externalId,
        public readonly string $eventTypeSlug,
        public readonly int $advisorId,
        public readonly CarbonImmutable $occurredOn,
        public readonly array $values,
        public readonly ?string $notes = null,
    ) {}
}
