<?php

declare(strict_types=1);

namespace App\Services\Funnels;

use App\Models\DocumentType;
use App\Models\FunnelRoute;
use App\Services\Catalog\RouteTotals;
use App\Services\Funnels\Data\FunnelValidationResult;
use App\Services\RouteComposition\Support\PresetDraftBuilder;
use App\Services\RouteValidation\Data\RuleWaivers;
use App\Services\RouteValidation\RouteValidator;
use Database\Seeders\DocumentTypeSeeder;

/**
 * Valida una ruta curada mientras se arma, no solo al generar el PDF.
 *
 * Con la rotación actual de campañas —lotes de 6 a 12 creativos cada una o dos
 * semanas— el volumen de rutas armadas a mano va a ser alto. El criterio de quien
 * redacta la promoción no puede ser la única defensa contra un error que el lead
 * va a leer con el nombre de la empresa encima.
 *
 * No conoce ninguna restricción. Traduce la ruta al borrador que el validador de
 * catálogo ya sabe leer y le entrega el resultado en el vocabulario de quien arma
 * el embudo. Las reglas siguen siendo filas de `catalog_rules`: sembrar una nueva
 * la hace aparecer aquí sola.
 */
final class FunnelRouteValidator
{
    public function __construct(
        private readonly PresetDraftBuilder $builder,
        private readonly RouteValidator $validator,
        private readonly FindingSuggester $suggester,
    ) {}

    /**
     * @param  DocumentType|null  $documentType  El documento con el que se emitirá.
     *                                           Por omisión, el gancho: es el que
     *                                           se entrega por enlace y el que el
     *                                           embudo alimenta.
     */
    public function validate(FunnelRoute $route, ?DocumentType $documentType = null): FunnelValidationResult
    {
        $route->loadMissing('items.course', 'funnel');

        return $this->validateDraft(
            courseIds: $route->orderedCourseIds(),
            anchorSpecializationId: $route->main_specialization_id,
            monthsByCourseId: $route->monthsByCourseId(),
            copyText: $route->copyText(),
            documentType: $documentType,
            waivedRuleCodes: $route->funnel?->waivedRuleCodes() ?? [],
        );
    }

    /**
     * El mismo veredicto sobre una ruta que todavía no se guarda.
     *
     * Es lo que sostiene el panel del editor: quien arma la promoción ve el
     * resultado mientras mueve cursos, no después de guardar. Comparte camino con
     * `validate()` a propósito —mismo borrador, mismo validador, mismas reglas—
     * porque una vista previa que evalúe distinto que la publicación es peor que
     * no tener vista previa.
     *
     * @param  list<int>  $courseIds  En el orden en que se presentarán.
     * @param  array<int, int|null>  $monthsByCourseId
     * @param  list<string>  $copyText  Texto de cara al lead, para el escaneo de fraseo.
     * @param  list<string>  $waivedRuleCodes  Lo que el embudo ignora, acotado a estos cursos.
     */
    public function validateDraft(
        array $courseIds,
        ?int $anchorSpecializationId = null,
        array $monthsByCourseId = [],
        array $copyText = [],
        ?DocumentType $documentType = null,
        array $waivedRuleCodes = [],
    ): FunnelValidationResult {
        $documentType ??= DocumentType::where('slug', DocumentTypeSeeder::ENFOCADA)->sole();

        $draft = $this->builder->build(
            documentType: $documentType,
            courseIds: $courseIds,
            anchorSpecializationId: $anchorSpecializationId,
            monthsByCourseId: $monthsByCourseId,
            copyText: $copyText,
            waivers: $waivedRuleCodes === [] ? null : new RuleWaivers($waivedRuleCodes, $courseIds),
        );

        return FunnelValidationResult::from(
            $this->validator->validate($draft),
            RouteTotals::for($this->builder->courses($courseIds)),
            $courseIds,
            $this->suggester,
        );
    }
}
