<?php

declare(strict_types=1);

namespace App\Http\Controllers\Funnels;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Funnels\Concerns\ProvidesEditorProps;
use App\Http\Requests\Funnels\ValidateFunnelDraftRequest;
use App\Models\Funnel;
use App\Services\Funnels\FunnelRouteValidator;
use App\Services\Funnels\MainSpecializationResolver;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El panel del validador, en vivo.
 *
 * Evalúa el borrador que está en pantalla sin guardarlo. No escribe nada: es una
 * lectura sobre datos propuestos, y por eso puede correr con cada cambio mientras
 * alguien mueve cursos.
 *
 * Usa el mismo validador que la publicación, no una versión aligerada. Un panel
 * que evalúe distinto que la puerta de publicación es peor que no tener panel:
 * enseñaría a desconfiar de él.
 */
class FunnelDraftValidationController extends Controller
{
    use ProvidesEditorProps;

    /** El armador de un embudo que todavía no existe. */
    public function create(
        ValidateFunnelDraftRequest $request,
        FunnelRouteValidator $validator,
        MainSpecializationResolver $mainSpecialization,
    ): Response {
        return Inertia::render('embudos/nuevo', [
            ...$this->editorProps($request),
            'validacionEnVivo' => $this->validateDraft($request, $validator, $mainSpecialization),
        ]);
    }

    public function store(
        ValidateFunnelDraftRequest $request,
        Funnel $funnel,
        FunnelRouteValidator $validator,
        MainSpecializationResolver $mainSpecialization,
    ): Response {
        return Inertia::render('embudos/editar', [
            ...$this->editorProps($request, $funnel),
            'embudo' => $this->funnelPayload($funnel->load('creator', 'primaryRoute.items')),
            'validacion' => $this->funnelValidation($funnel),
            'validacionEnVivo' => $this->validateDraft($request, $validator, $mainSpecialization),
        ]);
    }

    /** @return array<string, mixed> */
    private function validateDraft(
        ValidateFunnelDraftRequest $request,
        FunnelRouteValidator $validator,
        MainSpecializationResolver $mainSpecialization,
    ): array {
        /** @var list<array{course_id: int, suggested_month?: int|null}> $items */
        $items = $request->validated('items');
        $courseIds = array_map(fn (array $item): int => (int) $item['course_id'], $items);

        return $validator->validateDraft(
            courseIds: $courseIds,
            // Derivada de los cursos que hay en pantalla, no de la que quedó
            // guardada: es lo mismo que hace `FunnelWriter` al escribir la ruta.
            anchorSpecializationId: $mainSpecialization->for($courseIds),
            monthsByCourseId: $this->months($items),
            copyText: array_values(array_filter(
                $request->validated('copy_text'),
                fn (?string $text): bool => filled($text),
            )),
            // Un código que no sea dispensable se queda sin efecto en el
            // validador: `RuleWaivers` solo cubre reglas `waivable`.
            waivedRuleCodes: array_values($request->validated('waived_rules', [])),
        )->toArray();
    }

    /**
     * @param  list<array{course_id: int, suggested_month?: int|null}>  $items
     * @return array<int, int|null>
     */
    private function months(array $items): array
    {
        $months = [];

        foreach ($items as $item) {
            $months[(int) $item['course_id']] = $item['suggested_month'] ?? null;
        }

        return $months;
    }
}
