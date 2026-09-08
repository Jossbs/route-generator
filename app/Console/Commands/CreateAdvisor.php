<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * El registro público está deshabilitado: esta herramienta es interna.
 * Las cuentas de asesor se dan de alta con este comando.
 */
class CreateAdvisor extends Command
{
    protected $signature = 'advisor:create
                            {--name= : Nombre del asesor}
                            {--email= : Correo del asesor}';

    protected $description = 'Da de alta una cuenta de asesor';

    public function handle(): int
    {
        $name = $this->option('name') ?: text(
            label: 'Nombre del asesor',
            required: true,
        );

        $email = $this->option('email') ?: text(
            label: 'Correo del asesor',
            required: true,
        );

        $plainPassword = password(
            label: 'Contraseña',
            required: true,
        );

        $validator = validator(
            ['name' => $name, 'email' => $email, 'password' => $plainPassword],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $advisor = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($plainPassword),
        ]);

        $this->components->info("Asesor creado: {$advisor->email}");

        return self::SUCCESS;
    }
}
