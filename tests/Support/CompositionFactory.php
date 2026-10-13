<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\MembershipPlan;
use App\Models\Archetype;
use App\Models\DocumentType;
use App\Models\Lead;
use App\Models\Membership;
use App\Models\Specialization;
use App\Services\RouteComposition\Data\CompositionRequest;
use Database\Seeders\DocumentTypeSeeder;

/**
 * Arma solicitudes de composición desde slugs del catálogo sembrado, para que las
 * pruebas se lean como el caso del asesor y no como una lista de identificadores.
 */
final class CompositionFactory
{
    public static function enfocada(string $anchorSlug, ?string $archetypeSlug = 'general', ?Lead $lead = null): CompositionRequest
    {
        return self::request(DocumentTypeSeeder::ENFOCADA, $anchorSlug, $archetypeSlug, lead: $lead);
    }

    public static function anual(
        string $anchorSlug,
        ?string $archetypeSlug = 'general',
        bool $withMembership = true,
        ?Lead $lead = null,
    ): CompositionRequest {
        return self::request(DocumentTypeSeeder::ANUAL, $anchorSlug, $archetypeSlug, $withMembership, $lead);
    }

    private static function request(
        string $documentTypeSlug,
        string $anchorSlug,
        ?string $archetypeSlug,
        bool $withMembership = false,
        ?Lead $lead = null,
    ): CompositionRequest {
        $specialization = Specialization::where('slug', $anchorSlug)->sole();

        $lead ??= Lead::create([
            'name' => 'Lead de prueba',
            'phone' => '8990000000',
            'source' => 'formulario',
            'requested_specialization_id' => $specialization->id,
        ]);

        return new CompositionRequest(
            lead: $lead,
            documentType: DocumentType::where('slug', $documentTypeSlug)->sole(),
            anchorSpecialization: $specialization,
            archetype: $archetypeSlug === null ? null : Archetype::where('slug', $archetypeSlug)->sole(),
            membership: $withMembership ? self::membershipFor($lead) : null,
        );
    }

    private static function membershipFor(Lead $lead): Membership
    {
        return Membership::create([
            'lead_id' => $lead->id,
            'full_name' => 'Ana Torres Villarreal',
            'occupation' => 'Supervisora de producción',
            'plan' => MembershipPlan::ExpertAnual,
            'price' => '4199.00',
            'starts_on' => '2026-09-10',
            'ends_on' => '2027-09-09',
        ]);
    }
}
