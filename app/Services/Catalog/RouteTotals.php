<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Enums\CourseLevel;
use App\Models\Course;
use Illuminate\Support\Collection;

/**
 * Las tres cifras que el documento imprime, calculadas de forma que aguanten que
 * el lead las sume.
 *
 * Nunca se suman horas brutas. Los tres paquetes «Completo» absorben a sus propios
 * niveles, así que dentro de una misma unidad de contenido manda la duración que
 * el paquete declara: AutoCAD y SolidWorks reportan las 36 h del paquete, no las
 * 48 de la suma de sus tres niveles. Esa compresión de 12 h el sitio no la
 * explica y no se resuelve inventando: se usa el dato publicado del paquete.
 *
 * `courseCount` y `contentUnitCount` son las dos cifras que el catálogo declara
 * con la contención explícita —64 cursos, 55 programas— a escala de una ruta.
 */
final readonly class RouteTotals
{
    private function __construct(
        public int $courseCount,
        public int $contentUnitCount,
        public int $netHours,
        /** @var list<int> Cursos cuyas horas absorbió un paquete presente en la ruta. */
        public array $absorbedCourseIds,
    ) {}

    /** @param Collection<int, Course> $courses */
    public static function for(Collection $courses): self
    {
        $unique = $courses->unique('id');

        $netHours = 0;
        $absorbed = [];

        foreach ($unique->groupBy('content_unit_id') as $unit) {
            $package = $unit->first(fn (Course $c): bool => $c->level === CourseLevel::Completo);

            if ($package === null) {
                $netHours += (int) $unit->sum(fn (Course $c): int => (int) $c->duration_hours);

                continue;
            }

            // El paquete manda: sus horas ya cubren las de los niveles que absorbe.
            $netHours += (int) $package->duration_hours;

            foreach ($unit as $course) {
                if ($course->id !== $package->id) {
                    $absorbed[] = (int) $course->id;
                }
            }
        }

        return new self(
            courseCount: $unique->count(),
            contentUnitCount: $unique->pluck('content_unit_id')->unique()->count(),
            netHours: $netHours,
            absorbedCourseIds: $absorbed,
        );
    }

    /** @return array<string, int> */
    public function toArray(): array
    {
        return [
            'cursos' => $this->courseCount,
            'programas' => $this->contentUnitCount,
            'horas_netas' => $this->netHours,
        ];
    }
}
