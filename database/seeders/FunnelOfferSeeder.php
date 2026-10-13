<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Funnel;
use App\Models\User;
use App\Services\Funnels\FunnelWriter;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Las ofertas vigentes, cargadas como embudos.
 *
 * A diferencia de `FunnelSeeder`, esto sí es dato real y corre en producción. Pasa
 * por `FunnelWriter`, el mismo servicio que usa el armador, para que el embudo
 * quede igual que si Eje Cursos lo hubiera capturado a mano. Es idempotente: una
 * oferta que ya existe con ese nombre se reescribe en lugar de duplicarse.
 *
 * No está en `DatabaseSeeder`: las ofertas cambian con cada campaña y las pruebas
 * parten de una base sin embudos. Se corre aparte:
 * `php artisan db:seed --class=FunnelOfferSeeder`.
 */
class FunnelOfferSeeder extends Seeder
{
    public function run(FunnelWriter $writer): void
    {
        $author = User::query()->orderBy('id')->first();

        if ($author === null) {
            $this->command->warn('Sin usuarios en la base: las ofertas necesitan un autor.');

            return;
        }

        /** @var list<array{name: string, courses: list<string>}> $offers */
        $offers = require __DIR__.'/data/funnel_offers_2026_09.php';

        foreach ($offers as $offer) {
            $ids = Course::whereIn('slug', $offer['courses'])->pluck('id', 'slug');

            $missing = array_diff($offer['courses'], $ids->keys()->all());

            if ($missing !== []) {
                throw new RuntimeException("La oferta «{$offer['name']}» referencia cursos que no existen: ".implode(', ', $missing));
            }

            $courseIds = array_map(fn (string $slug): int => $ids[$slug], $offer['courses']);

            $existing = Funnel::where('internal_name', $offer['name'])->first();

            $existing === null
                ? $writer->create($offer['name'], $courseIds, $author)
                : $writer->update($existing, $offer['name'], $courseIds);
        }
    }
}
