<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'usuario@secureapp.test'],
            [
                'name' => 'Usuario de Prueba',
                'password' => Hash::make('Segura#2026!'),
            ]
        );

        // role y status se asignan explícitamente, no vía $fillable.
        $user->forceFill(['role' => 'admin', 'status' => 'active'])->save();
    }
}