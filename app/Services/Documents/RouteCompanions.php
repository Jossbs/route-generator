<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Models\Lead;
use App\Models\LearningRoute;
use Database\Seeders\DocumentTypeSeeder;

/** Presenta los textos vigentes sin reescribir un PDF ni su snapshot histórico. */
final class RouteCompanions
{
    public function __construct(
        private readonly AudioScript $audio,
        private readonly WrittenMessage $message,
    ) {}

    /** @return array{guion: array<string, mixed>|null, mensaje: array<string, mixed>|null} */
    public function for(LearningRoute $route, Lead $lead): array
    {
        $payload = $route->snapshot ?? [];

        if ($route->documentType->slug !== DocumentTypeSeeder::ENFOCADA) {
            return ['guion' => null, 'mensaje' => null];
        }

        if (($payload['guion']['version'] ?? null) === InitialRouteScriptCopy::VERSION
            && ($payload['mensaje']['version'] ?? null) === InitialRouteScriptCopy::VERSION) {
            return ['guion' => $payload['guion'], 'mensaje' => $payload['mensaje']];
        }

        return [
            'guion' => $this->audio->for($payload, $lead),
            'mensaje' => $this->message->for($payload, $lead),
        ];
    }
}
