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
            // Módulo médico (Fase 4 y 4B)
            'episodios.ver',
            'episodios.llegada',
            'episodios.triage',
            'episodios.ingreso',
            'episodios.atender',
            'episodios.egreso',
            'episodios.asignar_personal',
            'episodios.asignar_camilla',
            'episodios.crear_nn',
            'episodios.conciliar',
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
                'episodios.ver',
                'episodios.asignar_personal',
                'episodios.crear_nn',
                'episodios.conciliar',
            ],
            'coordinador' => [
                'correos.ver',
                'camillas.gestionar',
                'personal.gestionar',
                'turnos.gestionar',
                'ocupacion.ver',
                'episodios.ver',
                'episodios.llegada',
                'episodios.triage',
                'episodios.ingreso',
                'episodios.egreso',
                'episodios.asignar_personal',
                'episodios.asignar_camilla',
                'episodios.crear_nn',
                'episodios.conciliar',
            ],
            'medico' => [
                'ocupacion.ver',
                'episodios.ver',
                'episodios.triage',
                'episodios.ingreso',
                'episodios.atender',
                'episodios.egreso',
                'episodios.asignar_camilla',
                'episodios.crear_nn',
                'episodios.conciliar',
            ],
            'enfermero' => [
                'ocupacion.ver',
                'episodios.ver',
                'episodios.llegada',
                'episodios.triage',
                'episodios.ingreso',
                'episodios.atender',
                'episodios.asignar_camilla',
                'episodios.crear_nn',
            ],
            'admisiones' => [
                'ocupacion.ver',
                'episodios.ver',
                'episodios.llegada',
                'episodios.crear_nn',
                'episodios.conciliar',
            ],
            'auditor' => [
                'auditoria.ver',
                'episodios.ver',
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
