<?php

declare(strict_types=1);

namespace App\Http\Controllers\Funnels\Concerns;

use App\Http\Resources\Funnels\CourseOptionResource;
use App\Models\Course;
use App\Models\Funnel;
use App\Models\Specialization;
use App\Services\Funnels\CourseOverlap;
use App\Services\Funnels\FunnelRouteValidator;
use Illuminate\Http\Request;

/**
 * Los datos del armador, en un solo lugar.
 *
 * Los comparten la pantalla de edición y el endpoint de validación en vivo, y por
 * eso van como closures: en una recarga parcial Inertia descarta las propiedades
 * que no se pidieron antes de resolverlas, así que revalidar mientras alguien
 * mueve cursos no vuelve a leer el catálogo completo ni a recalcular los
 * traslapes.
 */
trait ProvidesEditorProps
{
    /** @return array<string, mixed> */
    protected function editorProps(Request $request, ?Funnel $funnel = null): array
    {
        return [
            'catalogo' => fn (): array => CourseOptionResource::collection(
                Course::query()->where('is_active', true)->orderBy('name')->get(),
            )->resolve(),
            'especialidades' => fn (): array => $this->specializations(),
            // El aviso de «este curso ya está en otro embudo». Nunca impide
            // guardar: un curso puede enganchar desde dos ramas a propósito.
            'traslapes' => fn (): array => app(CourseOverlap::class)->forFunnel($funnel),
            'permisos' => fn (): array => [
                'editar' => $funnel === null
                    ? ($request->user()?->can('create', Funnel::class) ?? false)
                    : ($request->user()?->can('update', $funnel) ?? false),
                'eliminar' => $funnel !== null && ($request->user()?->can('delete', $funnel) ?? false),
            ],
        ];
    }

    /**
     * La ficha del embudo tal como la lee el armador.
     *
     * @return array<string, mixed>
     */
    protected function funnelPayload(Funnel $funnel): array
    {
        $route = $funnel->primaryRoute;

        return [
            'id' => (int) $funnel->id,
            'nombre' => $funnel->internal_name,
            'slug' => $funnel->slug,
            'creado_en' => $funnel->created_at?->toIso8601String(),
            'autor' => $funnel->creator?->name,
            'especialidad_principal_id' => $route?->main_specialization_id,
            'cursos' => $route === null ? [] : $route->orderedCourseIds(),
            'reglas_ignoradas' => $funnel->waivedRuleCodes(),
        ];
    }

    /**
     * El veredicto del validador sobre la lista guardada.
     *
     * @return array<string, mixed>
     */
    protected function funnelValidation(Funnel $funnel): array
    {
        $route = $funnel->primaryRoute;

        if ($route === null) {
            return app(FunnelRouteValidator::class)->validateDraft(courseIds: [])->toArray();
        }

        return app(FunnelRouteValidator::class)->validate($route)->toArray();
    }

    /** @return list<array{id: int, nombre: string, roles_permitidos: array<string, bool>|null}> */
    private function specializations(): array
    {
        return array_values(Specialization::query()
            ->orderBy('display_order')
            ->get()
            ->map(fn (Specialization $s): array => [
                'id' => (int) $s->id,
                'nombre' => $s->name,
                'roles_permitidos' => $s->allowed_roles,
            ])
            ->all());
    }
}
