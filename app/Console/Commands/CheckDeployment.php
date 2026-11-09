<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CatalogRule;
use App\Models\Course;
use App\Models\DocumentType;
use App\Services\Identity\CurrentAdvisor;
use App\Services\Identity\SeededAdvisor;
use Database\Seeders\DocumentTypeSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class CheckDeployment extends Command
{
    protected $signature = 'deploy:check';

    protected $description = 'Comprueba configuración, identidad, catálogo y servicios antes de publicar.';

    /** @var list<array{string, string, string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->results = [];
        $this->check('Entorno', fn (): bool => app()->isProduction() && ! config('app.debug'), 'APP_ENV=production y APP_DEBUG=false.');
        $this->check('Clave persistente', $this->validKey(...), 'Configura una APP_KEY válida y conserva su respaldo.');
        $this->check('URL pública', fn (): bool => filter_var(config('app.url'), FILTER_VALIDATE_URL) !== false
            && parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https', 'APP_URL debe ser una URL HTTPS.');
        $this->check('Identidad', fn (): bool => $this->productionAdvisor(app(CurrentAdvisor::class)), 'Conecta CurrentAdvisor con el sistema anfitrión; no uses el asesor de desarrollo.');
        $this->check('Cookies', fn (): bool => config('session.secure') === true && config('session.http_only') === true, 'Activa SESSION_SECURE_COOKIE y SESSION_HTTP_ONLY.');
        $this->check('PostgreSQL', function (): bool {
            return DB::connection()->getDriverName() === 'pgsql' && DB::selectOne('SELECT 1') !== null;
        }, 'Comprueba conexión y credenciales de PostgreSQL.');
        $this->check('Migraciones', $this->migrationsCurrent(...), 'Aplica las migraciones pendientes después de respaldar.');
        $this->check('Catálogo inicial', fn (): bool => Course::query()->where('is_active', true)->exists()
            && CatalogRule::query()->inEffect()->exists()
            && DocumentType::query()->whereIn('slug', [DocumentTypeSeeder::ENFOCADA, DocumentTypeSeeder::ANUAL])->count() === 2,
            'En una instalación nueva ejecuta ProductionCatalogSeeder.');
        $this->check('Cola y tiempos', fn (): bool => config('queue.default') === 'database'
            && (int) config('queue.connections.database.retry_after') > 90
            && (int) config('documents.gotenberg.timeout') > 0
            && (int) config('documents.gotenberg.timeout') < 90,
            'Usa cola database, retry_after > 90 y timeout de Gotenberg entre 1 y 89 segundos.');
        $this->check('Gotenberg', function (): bool {
            $response = Http::timeout(5)->get(rtrim((string) config('documents.gotenberg.url'), '/').'/health');

            return config('documents.renderer') === 'gotenberg' && $response->successful() && $response->json('status') === 'up';
        }, 'Configura DOCUMENT_RENDERER=gotenberg y un servicio saludable.');
        $this->check('Assets de producción', fn (): bool => is_file(public_path('build/manifest.json')) && ! is_file(public_path('hot')), 'Compila los assets y elimina public/hot del artefacto.');
        $this->check('Persistencia y permisos', fn (): bool => is_writable(storage_path('app/private'))
            && is_writable(storage_path('framework/views')) && is_writable(app()->bootstrapPath('cache')),
            'App y worker necesitan el mismo almacenamiento privado persistente.');

        $this->table(['Comprobación', 'Estado', 'Acción si falla'], $this->results);
        $failed = collect($this->results)->contains(fn (array $row): bool => $row[1] === 'ERROR');

        if ($failed) {
            $this->error('El despliegue todavía tiene requisitos pendientes.');

            return self::FAILURE;
        }

        $this->info('Comprobaciones aprobadas. Verifica también el acceso de un asesor y un PDF procesado por el worker.');

        return self::SUCCESS;
    }

    private function check(string $name, callable $test, string $action): void
    {
        try {
            $passed = $test();
        } catch (Throwable) {
            // El diagnóstico no imprime excepciones que puedan incluir credenciales.
            $passed = false;
        }

        $this->results[] = [$name, $passed ? 'OK' : 'ERROR', $passed ? '' : $action];
    }

    private function validKey(): bool
    {
        $key = (string) config('app.key');
        $decoded = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;

        return is_string($decoded) && Encrypter::supported($decoded, (string) config('app.cipher'));
    }

    private function productionAdvisor(CurrentAdvisor $advisor): bool
    {
        return ! ($advisor instanceof SeededAdvisor);
    }

    private function migrationsCurrent(): bool
    {
        $migrator = app(Migrator::class);
        $repository = $migrator->getRepository();

        return $repository->repositoryExists()
            && array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $repository->getRan()) === [];
    }
}
