<?php

declare(strict_types=1);

use App\Models\Funnel;
use App\Models\User;
use App\Services\Identity\SeededAdvisor;
use Database\Seeders\AdvisorSeeder;
use Database\Seeders\DatabaseSeeder;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
 * La costura de identidad.
 *
 * El módulo se inserta en un sistema que ya tiene su propio acceso, así que aquí
 * no hay login ni registro. Pero media docena de columnas apuntan a `users`, y
 * `published_by` es lo que permite auditar después quién puso un documento frente
 * a un lead: que no haya pantalla de acceso no puede significar que no haya autor.
 */

it('no expone ninguna pantalla de acceso', function (string $ruta) {
    $this->seed(AdvisorSeeder::class);

    $this->get($ruta)->assertNotFound();
})->with([
    '/login',
    '/register',
    '/forgot-password',
    '/settings/profile',
    '/settings/security',
]);

it('resuelve al asesor sin que nadie inicie sesión', function () {
    $this->seed(DatabaseSeeder::class);

    $this->get('/embudos')->assertOk();
});

it('firma con ese asesor lo que se crea', function () {
    $this->seed(DatabaseSeeder::class);

    $this->post('/embudos', [
        'name' => 'Calidad octubre',
        'items' => [],
    ])->assertRedirect();

    $advisor = User::where('email', config('identity.development_advisor'))->sole();

    expect(Funnel::sole()->created_by)->toBe($advisor->id);
});

it('se detiene con un mensaje claro si no hay a quién resolver', function () {
    // Sin asesores en la base no hay autor posible. Una fila sin autor no es un
    // dato de menos: es un documento del que nadie responde.
    expect(User::count())->toBe(0);

    $this->withoutExceptionHandling()
        ->get('/embudos');
})->throws(HttpException::class, 'No hay ningún asesor que resolver.');

it('no redirige a ninguna pantalla de acceso cuando no hay asesor', function () {
    $this->get('/embudos')->assertStatus(503);
});

it('deja que el anfitrión imponga otro asesor', function () {
    // Lo que hará el sistema anfitrión: entregar su propia sesión. El guard la
    // respeta en vez de sobrescribirla.
    $this->seed(DatabaseSeeder::class);

    $huesped = User::create(['name' => 'Asesor del anfitrión', 'email' => 'anfitrion@dpsys.com.mx']);

    $this->actingAs($huesped)
        ->post('/embudos', [
            'name' => 'Lean noviembre',
            'items' => [],
        ])->assertRedirect();

    expect(Funnel::sole()->created_by)->toBe($huesped->id);
});

it('no suplanta a otro usuario si falta el asesor configurado', function () {
    User::factory()->create(['email' => 'otro@dpsys.com.mx']);

    expect((new SeededAdvisor('ausente@dpsys.com.mx'))->resolve())->toBeNull();
    $this->get('/embudos')->assertStatus(503);
});

it('no permite acceso anónimo con el asesor de desarrollo en producción', function () {
    $this->seed(AdvisorSeeder::class);
    $this->app->instance('env', 'production');

    expect((new SeededAdvisor((string) config('identity.development_advisor')))->resolve())->toBeNull();
    $this->get('/embudos')->assertStatus(503);
});
