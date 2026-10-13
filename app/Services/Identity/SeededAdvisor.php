<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Models\User;

/**
 * La implementación de desarrollo: el asesor sembrado.
 *
 * Sirve para trabajar sobre el módulo antes de que exista el sistema anfitrión, y
 * para nada más. Resuelve siempre la misma cuenta —la que `AdvisorSeeder` deja en
 * la base— de modo que la autoría de lo que se cree mientras tanto sea
 * consistente y no una fila anónima.
 *
 * Cuando el anfitrión provea la sesión, esta clase se reemplaza en el contenedor
 * por la suya y nada más cambia: ni los controladores, ni las policies, ni las
 * columnas.
 */
final class SeededAdvisor implements CurrentAdvisor
{
    public function __construct(private readonly string $email) {}

    public function resolve(): ?User
    {
        if (! app()->environment(['local', 'testing'])) {
            return null;
        }

        return User::query()->where('email', $this->email)->first();
    }
}
