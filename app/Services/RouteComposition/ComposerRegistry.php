<?php

declare(strict_types=1);

namespace App\Services\RouteComposition;

use App\Models\DocumentType;
use App\Services\RouteComposition\Contracts\RouteComposer;
use App\Services\RouteComposition\Exceptions\CompositionException;
use Illuminate\Contracts\Container\Container;

/**
 * Resuelve el compositor que sabe armar cada tipo de documento.
 *
 * Los tipos de documento son datos, pero componer uno nuevo sí requiere código:
 * un tercer documento entra como INSERT en `document_types` más un compositor
 * registrado aquí. Lo que no requiere código es cambiarle la forma a uno
 * existente, porque eso vive en `composition_policy`.
 */
final class ComposerRegistry
{
    /** @var array<string, class-string<RouteComposer>> */
    private array $composers = [];

    public function __construct(private readonly Container $container) {}

    /** @param class-string<RouteComposer> $composer */
    public function register(string $composer): self
    {
        $this->composers[$composer::documentType()] = $composer;

        return $this;
    }

    public function for(DocumentType $documentType): RouteComposer
    {
        $composer = $this->composers[$documentType->slug] ?? null;

        if ($composer === null) {
            throw CompositionException::noComposerFor($documentType);
        }

        return $this->container->make($composer);
    }

    /** @return list<string> */
    public function documentTypes(): array
    {
        return array_keys($this->composers);
    }
}
