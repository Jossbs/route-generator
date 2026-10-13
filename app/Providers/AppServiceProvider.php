<?php

namespace App\Providers;

use App\Models\Funnel;
use App\Models\Membership;
use App\Models\User;
use App\Observers\MembershipSalesObserver;
use App\Policies\FunnelPolicy;
use App\Services\Documents\DocumentRenderer;
use App\Services\Documents\GotenbergRenderer;
use App\Services\Documents\PrintablePageRenderer;
use App\Services\Identity\CurrentAdvisor;
use App\Services\Identity\SeededAdvisor;
use App\Services\RouteComposition\ComposerRegistry;
use App\Services\RouteComposition\Composers\AnnualRouteComposer;
use App\Services\RouteComposition\Composers\FocusedRouteComposer;
use App\Services\RouteValidation\RuleInterpreterRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Los intérpretes no tienen estado: uno por proceso alcanza, y tenerlo
        // en el contenedor deja registrar tipos nuevos desde una prueba.
        $this->app->singleton(RuleInterpreterRegistry::class);

        // Los compositores sí tienen estado por solicitud, así que el registro
        // guarda la clase y el contenedor construye uno nuevo cada vez.
        $this->app->singleton(ComposerRegistry::class, fn ($app): ComposerRegistry => (new ComposerRegistry($app))
            ->register(FocusedRouteComposer::class)
            ->register(AnnualRouteComposer::class));

        // El PDF lo produce Chromium headless dentro de Gotenberg. La otra
        // implementación —cerrar el ciclo de estados y dejar que el asesor
        // imprima— sigue existiendo para las pruebas y para el día que el
        // contenedor no esté: ver config/documents.php.
        $this->app->bind(DocumentRenderer::class, fn ($app): DocumentRenderer => config('documents.renderer') === 'gotenberg'
            ? $app->make(GotenbergRenderer::class)
            : $app->make(PrintablePageRenderer::class));

        // El módulo no autentica: lo inserta un sistema que ya tiene su propio
        // acceso. Mientras ese sistema no exista, el asesor sale de la base.
        // Enchufar el anfitrión es cambiar esta línea por su implementación.
        $this->app->bind(CurrentAdvisor::class, fn (): SeededAdvisor => new SeededAdvisor(
            (string) config('identity.development_advisor'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureIdentity();
        $this->configureAuthorization();
    }

    /**
     * El guard que resuelve al asesor sin pedirle que entre.
     *
     * `viaRequest` da un guard sin estado: no hay sesión de acceso que iniciar ni
     * cerrar, y aun así `$request->user()`, el middleware `auth` y las policies
     * siguen funcionando igual que con el guard de sesión. Ese es justamente el
     * punto: el resto del módulo no se entera de que aquí no hay login.
     */
    protected function configureIdentity(): void
    {
        Auth::viaRequest('advisor', fn (): ?User => $this->app->make(CurrentAdvisor::class)->resolve());
    }

    protected function configureAuthorization(): void
    {
        Gate::policy(Funnel::class, FunnelPolicy::class);

        // Capturar eventos, fijar metas, cerrar meses y configurar KPIs. El
        // tablero no pasa por aquí: lo ve todo ventas.
        Gate::define('manage-kpis', fn (User $user): bool => $user->is_kpi_admin);

        // Cada membresía que registra el generador de rutas se sugiere como
        // venta al módulo de KPIs.
        Membership::observe(MembershipSalesObserver::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
