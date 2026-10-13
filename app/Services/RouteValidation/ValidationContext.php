<?php

declare(strict_types=1);

namespace App\Services\RouteValidation;

use App\Services\RouteValidation\Data\RouteDraft;

final readonly class ValidationContext
{
    public function __construct(
        public RouteDraft $draft,
        public CatalogLookup $catalog,
    ) {}

    public static function for(RouteDraft $draft): self
    {
        return new self($draft, new CatalogLookup($draft));
    }
}
