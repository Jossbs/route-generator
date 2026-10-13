<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * El asesor que firma lo que se cree mientras no exista el sistema anfitrión.
 *
 * No es un usuario de prueba desechable: es el autor que queda en `created_by` y
 * `published_by` de todo lo que se arme en esta etapa, así que lleva el nombre
 * real del área que cura las rutas —Eje Cursos— y no «test».
 *
 * Cuando el módulo se inserte, los asesores vendrán del sistema anfitrión y este
 * seeder deja de correr. Ver App\Services\Identity\CurrentAdvisor.
 */
class AdvisorSeeder extends Seeder
{
    public function run(): void
    {
        // updateOrCreate y no firstOrCreate: el nombre es lo que el asesor ve
        // arriba a la derecha, así que corregirlo aquí tiene que alcanzar a la
        // cuenta que ya existe en vez de quedarse en el seeder.
        $whatsapp = config('identity.development_advisor_whatsapp');

        User::updateOrCreate(
            ['email' => config('identity.development_advisor')],
            [
                'name' => 'Eje Cursos',
                // Mientras no haya roles del anfitrión, la cuenta de desarrollo
                // es también quien administra los KPIs.
                'is_kpi_admin' => true,
                // Sin número configurado no se pisa el que ya esté cargado: el
                // perfil del asesor es del sistema anfitrión, y un seeder no
                // tiene por qué vaciarlo cada vez que corre.
                ...(filled($whatsapp) ? ['whatsapp' => $whatsapp] : []),
            ],
        );
    }
}
