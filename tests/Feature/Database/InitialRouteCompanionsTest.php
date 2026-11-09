<?php

declare(strict_types=1);

use App\Models\CopyBlock;
use App\Services\Documents\InitialRouteScriptCopy;
use Database\Seeders\CopySeeder;

it('actualiza instalaciones existentes y retira las variantes anteriores del guion', function () {
    $this->seed(CopySeeder::class);
    CopyBlock::where('slug', 'guion.saludo')->update(['body' => 'Saludo anterior']);
    CopyBlock::create([
        'slug' => 'guion.ruta.tres',
        'scope' => 'guion_audio',
        'locale' => 'es_MX',
        'body' => 'Tres cursos extra',
        'status' => 'aprobado',
    ]);

    $migration = require database_path('migrations/2026_11_08_130000_update_initial_route_companions.php');
    $migration->up();
    $migration->up();

    foreach (InitialRouteScriptCopy::slots() as $slot) {
        $block = CopyBlock::where('slug', $slot['slug'])->sole();
        expect($block->body)->toBe($slot['body'])
            ->and($block->status)->toBe('aprobado');
    }

    expect(CopyBlock::whereIn('slug', InitialRouteScriptCopy::retiredSlugs())->count())->toBe(0);
});
