<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'usuarios.gestionar',
            'auditoria.ver',
            'correos.ver',
            'sedes.gestionar',
            'habitaciones.gestionar',
            'camillas.gestionar',
            'personal.gestionar',
            'turnos.gestionar',
            'ocupacion.ver',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $roles = [
            'administrador' => [
                'usuarios.gestionar',
                'correos.ver',
                'sedes.gestionar',
                'habitaciones.gestionar',
                'camillas.gestionar',
                'personal.gestionar',
                'turnos.gestionar',
                'ocupacion.ver',
            ],
            'coordinador' => [
                'correos.ver',
                'camillas.gestionar',
                'personal.gestionar',
                'turnos.gestionar',
                'ocupacion.ver',
            ],
            'auditor' => [
                'auditoria.ver',
            ],
            'medico' => [
                'ocupacion.ver',
            ],
            'enfermero' => [
                'ocupacion.ver',
            ],
            'admisiones' => [
                'ocupacion.ver',
            ],
            'editor' => [
                'correos.ver',
            ],
            'autor' => [],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            Role::findOrCreate($roleName, 'web')->syncPermissions($rolePermissions);
        }
    }
}
