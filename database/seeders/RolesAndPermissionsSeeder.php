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
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $roles = [
            'administrador' => ['usuarios.gestionar', 'correos.ver'],
            'auditor' => ['auditoria.ver'],
            'coordinador' => ['correos.ver'],
            'editor' => ['correos.ver'],
            'autor' => [],
            'medico' => [],
            'enfermero' => [],
            'admisiones' => [],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            Role::findOrCreate($roleName, 'web')->syncPermissions($rolePermissions);
        }
    }
}
