<?php

declare(strict_types=1);

namespace App\Services\Funnels;

use App\Enums\FunnelState;
use App\Models\Funnel;
use App\Models\FunnelRoute;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Crea y reescribe un embudo: su nombre y sus cursos.
 *
 * Vive fuera del controlador por la razón de siempre —hoy lo llama un formulario,
 * mañana podría llamarlo una importación— y porque lo que se guarda son tres
 * filas en tres tablas: el embudo, su ruta y su composición. A medias no sirve,
 * así que van en una transacción.
 *
 * El embudo nace disponible. No hay paso de publicación: se captura para usarse,
 * y el asesor lo ve en su selector en cuanto tiene cursos. El estado sigue en la
 * tabla porque el ciclo de vida completo —pausar, archivar— es post-v1.
 */
final class FunnelWriter
{
    public function __construct(
        private readonly FunnelRouteWriter $routes,
        private readonly MainSpecializationResolver $mainSpecialization,
    ) {}

    /**
     * @param  list<int>  $courseIds
     * @param  list<string>  $waivedRuleCodes
     */
    public function create(string $name, array $courseIds, User $advisor, array $waivedRuleCodes = []): Funnel
    {
        return DB::transaction(function () use ($name, $courseIds, $advisor, $waivedRuleCodes): Funnel {
            $funnel = Funnel::create([
                'slug' => Funnel::uniqueSlugFor($name),
                'internal_name' => $name,
                'state' => FunnelState::Activo,
                'created_by' => $advisor->id,
                'waived_rules' => $this->waivers([], $waivedRuleCodes, $advisor),
            ]);

            $this->writeRoute($funnel, $name, $courseIds);

            return $funnel->refresh();
        });
    }

    /**
     * @param  list<int>  $courseIds
     * @param  list<string>|null  $waivedRuleCodes  Null deja las que ya tenía.
     */
    public function update(Funnel $funnel, string $name, array $courseIds, ?User $editor = null, ?array $waivedRuleCodes = null): Funnel
    {
        return DB::transaction(function () use ($funnel, $name, $courseIds, $editor, $waivedRuleCodes): Funnel {
            // El slug no se recalcula al renombrar: es la llave con la que el
            // snapshot de cada documento ya emitido se refiere a este embudo.
            $funnel->update([
                'internal_name' => $name,
                'waived_rules' => $waivedRuleCodes === null
                    ? $funnel->waived_rules
                    : $this->waivers($funnel->waived_rules ?? [], $waivedRuleCodes, $editor),
            ]);

            $this->writeRoute($funnel, $name, $courseIds);

            return $funnel->refresh();
        });
    }

    /**
     * Quién ignoró qué y cuándo. Una regla que ya estaba ignorada conserva su
     * firma original: volver a guardar el embudo no es volver a decidirlo.
     *
     * @param  list<array{code: string, by: int|null, at: string}>  $current
     * @param  list<string>  $codes
     * @return list<array{code: string, by: int|null, at: string}>
     */
    private function waivers(array $current, array $codes, ?User $by): array
    {
        $existing = collect($current)->keyBy('code');

        return array_map(
            fn (string $code): array => $existing->get($code) ?? [
                'code' => $code,
                'by' => $by?->id,
                'at' => now()->toIso8601String(),
            ],
            array_values(array_unique($codes)),
        );
    }

    /** @param list<int> $courseIds */
    private function writeRoute(Funnel $funnel, string $name, array $courseIds): void
    {
        $route = $funnel->primaryRoute()->first() ?? new FunnelRoute(['funnel_id' => $funnel->id, 'position' => 0]);

        $route->fill([
            'funnel_id' => $funnel->id,
            'name' => $name,
            // La especialidad que abre el embudo se deriva de los cursos: es de
            // donde sale de todos modos, y es la que ocupa el rol de ancla cuando
            // el validador evalúa la lista.
            'main_specialization_id' => $this->mainSpecialization->for($courseIds),
        ])->save();

        $this->routes->sync($route, array_map(
            fn (int $id): array => ['course_id' => $id],
            $courseIds,
        ));
    }
}
