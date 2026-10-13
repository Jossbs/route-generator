<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Models\User;

/**
 * Quién está usando el módulo.
 *
 * Este módulo no autentica a nadie: se inserta en un sistema que ya tiene su
 * propio acceso, y duplicar ahí un login sería pedirle a la misma persona que
 * entre dos veces. Pero el módulo sí necesita saber quién actúa —`created_by` y
 * `published_by` son lo que permite auditar después quién publicó qué frente a un
 * lead— así que la identidad entra por esta costura en vez de por una pantalla.
 *
 * Es la misma forma que `LeadSource` en docs/ARQUITECTURA.md §3: una interfaz con dos
 * implementaciones posibles, de las que hoy solo se escribe una. La del sistema
 * anfitrión se escribe cuando se sepa cómo integra —sesión compartida, cabecera
 * firmada, lo que sea— y no antes: inventarla ahora sería adivinar un contrato
 * ajeno.
 */
interface CurrentAdvisor
{
    /** El asesor que actúa, o null si no se pudo resolver ninguno. */
    public function resolve(): ?User;
}
