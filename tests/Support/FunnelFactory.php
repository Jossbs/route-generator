<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\FunnelState;
use App\Models\Course;
use App\Models\Funnel;
use App\Models\FunnelRoute;
use App\Models\Specialization;
use App\Models\User;

/**
 * Arma embudos desde slugs del catálogo sembrado, para que las pruebas se lean
 * como el caso de quien redacta la promoción y no como una lista de identificadores.
 */
final class FunnelFactory
{
    /**
     * @param  list<string>  $courseSlugs  En el orden en que se presentarán.
     * @param  array<string, mixed>  $routeAttributes
     * @param  array<string, mixed>  $funnelAttributes
     */
    public static function route(
        array $courseSlugs,
        ?string $mainSpecializationSlug = null,
        array $routeAttributes = [],
        array $funnelAttributes = [],
    ): FunnelRoute {
        $funnel = self::funnel($funnelAttributes);

        $route = FunnelRoute::create([
            'funnel_id' => $funnel->id,
            'name' => 'Ruta de prueba',
            'position' => 1,
            'main_specialization_id' => $mainSpecializationSlug === null
                ? null
                : Specialization::where('slug', $mainSpecializationSlug)->sole()->id,
            'duration_months' => 12,
            ...$routeAttributes,
        ]);

        self::attach($route, $courseSlugs);

        return $route->refresh();
    }

    /** @param array<string, mixed> $attributes */
    public static function funnel(array $attributes = []): Funnel
    {
        return Funnel::create([
            'slug' => $attributes['slug'] ?? 'ruta-de-prueba',
            'internal_name' => 'EJEMPLO · ruta de prueba',
            // Ángulo, nombre público y vigencia están dormidos: no se capturan y
            // las pruebas no los inventan salvo que prueben justamente eso.
            'state' => FunnelState::Borrador,
            'created_by' => self::author()->id,
            ...$attributes,
        ]);
    }

    /**
     * Una ruta curada lista para que el asesor la elija: activa y con cursos.
     *
     * @param  list<string>  $courseSlugs
     * @param  array<string, mixed>  $funnelAttributes
     */
    public static function available(
        array $courseSlugs,
        ?string $mainSpecializationSlug = null,
        array $funnelAttributes = [],
    ): FunnelRoute {
        return self::route($courseSlugs, $mainSpecializationSlug, [], [
            'state' => FunnelState::Activo,
            ...$funnelAttributes,
        ]);
    }

    /** @param list<string> $courseSlugs */
    public static function attach(FunnelRoute $route, array $courseSlugs): void
    {
        $courses = Course::whereIn('slug', $courseSlugs)->pluck('id', 'slug');

        $missing = array_diff($courseSlugs, $courses->keys()->all());

        if ($missing !== []) {
            throw new \RuntimeException('La prueba referencia cursos que no existen: '.implode(', ', $missing));
        }

        foreach (array_values($courseSlugs) as $index => $slug) {
            $route->items()->create([
                'course_id' => $courses[$slug],
                'position' => $index + 1,
                'suggested_month' => $index + 1,
            ]);
        }
    }

    public static function author(): User
    {
        return User::firstOrCreate(
            ['email' => 'mercadotecnia@dpsys.com.mx'],
            ['name' => 'Eje Cursos', 'password' => 'password'],
        );
    }
}
