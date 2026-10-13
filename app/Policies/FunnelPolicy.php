<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Funnel;
use App\Models\User;

/**
 * Quién puede tocar un embudo.
 *
 * El sistema todavía no tiene roles: la identidad entra por la costura
 * `CurrentAdvisor` y toda cuenta es de asesor, así que hoy todas las respuestas
 * son la misma. La policy existe igual para que el día que el sistema anfitrión
 * traiga sus roles, restringir sea una línea aquí y no una cacería por los
 * controladores.
 *
 * Quién puede publicar una promoción —plausiblemente no quien la redacta— es una
 * decisión que sigue pendiente (MODULO_EMBUDOS.md §11.3) y que hoy no tiene dónde
 * aplicarse: el embudo nace disponible y no hay paso de publicación.
 */
class FunnelPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Funnel $funnel): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** Un embudo archivado es histórico: no se edita, ni siquiera por su autor. */
    public function update(User $user, Funnel $funnel): bool
    {
        return $funnel->state->isEditable();
    }

    public function delete(User $user, Funnel $funnel): bool
    {
        return ! $funnel->isPublished();
    }
}
