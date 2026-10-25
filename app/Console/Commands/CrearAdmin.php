<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CrearAdmin extends Command
{
    protected $signature = 'app:crear-admin';

    protected $description = 'Crea una cuenta con rol administrador y auditor';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Nombre completo'));
        $email = strtolower(trim((string) $this->ask('Correo electrónico')));
        $password = (string) $this->secret('Contraseña (no se muestra)');
        $confirmation = (string) $this->secret('Confirmar contraseña');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'name' => ['required', 'string', 'min:2', 'max:100'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $user->forceFill(['status' => 'active'])->save();
        $user->syncRoles(['administrador', 'auditor']);

        $this->info("Cuenta creada: {$email}");

        return self::SUCCESS;
    }
}