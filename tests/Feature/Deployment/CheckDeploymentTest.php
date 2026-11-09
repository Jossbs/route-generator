<?php

declare(strict_types=1);

use App\Models\Funnel;
use App\Models\User;
use App\Services\Identity\CurrentAdvisor;
use App\Services\Identity\SeededAdvisor;
use Database\Seeders\ProductionCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DeploymentHostAdvisor implements CurrentAdvisor
{
    public function resolve(): ?User
    {
        return null;
    }
}

beforeEach(function () {
    $this->seed(ProductionCatalogSeeder::class);
    $this->app->instance('env', 'production');
    $this->app->usePublicPath(sys_get_temp_dir().'/route-generator-deploy-test-'.Str::uuid());
    File::ensureDirectoryExists(public_path('build'));
    File::put(public_path('build/manifest.json'), '{}');
    config([
        'app.debug' => false,
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'app.url' => 'https://rutas.example.test',
        'identity.current_advisor' => DeploymentHostAdvisor::class,
        'session.secure' => true,
        'session.http_only' => true,
        'queue.default' => 'database',
        'queue.connections.database.retry_after' => 120,
        'documents.renderer' => 'gotenberg',
        'documents.gotenberg.timeout' => 60,
    ]);
    Http::fake(['*/health' => Http::response(['status' => 'up'])]);
});

afterEach(function () {
    if (str_starts_with(public_path(), sys_get_temp_dir().'/route-generator-deploy-test-')) {
        File::deleteDirectory(public_path());
    }
});

it('aprueba un entorno completo sin sembrar usuarios ni embudos de desarrollo', function () {
    expect(User::count())->toBe(0)->and(Funnel::count())->toBe(0);
    expect(app(CurrentAdvisor::class))->toBeInstanceOf(DeploymentHostAdvisor::class);
    $this->artisan('deploy:check')->assertSuccessful();
});

it('rechaza configuración de desarrollo y no muestra claves en el diagnóstico', function () {
    config([
        'app.debug' => true,
        'app.url' => 'http://rutas.example.test',
        'identity.current_advisor' => SeededAdvisor::class,
        'session.secure' => false,
    ]);

    $this->artisan('deploy:check')
        ->expectsOutputToContain('El despliegue todavía tiene requisitos pendientes.')
        ->doesntExpectOutputToContain(config('app.key'))
        ->assertFailed();
});

it('detecta una migración pendiente antes de publicar', function () {
    DB::table('migrations')->where('migration', '2026_11_08_150000_replace_google_trust_metric_with_foundation_year')->delete();

    $this->artisan('deploy:check')->assertFailed();
});

it('no acepta un servicio de PDF caído ni un timeout incompatible con el trabajo', function () {
    Http::fake(['*/health' => Http::response([], 503)]);
    config(['documents.gotenberg.timeout' => 100]);

    $this->artisan('deploy:check')->assertFailed();
});

it('rechaza un artefacto que todavía apunta al servidor Vite de desarrollo', function () {
    File::put(public_path('hot'), 'http://localhost:5173');

    $this->artisan('deploy:check')->assertFailed();
});

it('explica una implementación de identidad incompatible', function () {
    config(['identity.current_advisor' => stdClass::class]);

    expect(fn () => app(CurrentAdvisor::class))
        ->toThrow(LogicException::class, 'CURRENT_ADVISOR_CLASS debe implementar');
    $this->artisan('deploy:check')->assertFailed();
});
