<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'usuario@secureapp.test'],
            [
                'name' => 'Usuario de Prueba',
                'password' => Hash::make('Segura#2026!'),
            ]
        );
    }
}