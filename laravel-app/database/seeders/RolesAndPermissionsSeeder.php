<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Crear Permisos Básicos (Demo)
        $permissions = [
            'manage_users',
            'view_residents',
            'create_residents',
            'edit_residents',
            'delete_residents',
            'manage_medications',
            'administer_medications',
            'view_reports',
            'manage_inventory',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // 2. Crear Roles y asignar permisos
        // El sistema tiene exactamente 3 perfiles: Admin (administradora), Enfermera y Staff.
        // El rol Doctor existió en una versión anterior y fue eliminado: en el hogar es la
        // enfermera quien transcribe la indicación médica al sistema, no un médico interno.

        // ADMINISTRADORA: Todo
        $roleAdmin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $roleAdmin->givePermissionTo(Permission::all());

        // ENFERMERA
        // Marca medicamentos como administrados/no administrados (su tarea principal) y
        // además registra las prescripciones y sus horarios (manage_medications), porque
        // es quien traslada la indicación médica al sistema. No crea ni edita residentes,
        // no gestiona personal y no registra movimientos de inventario.
        $roleNurse = Role::firstOrCreate(['name' => 'Enfermera', 'guard_name' => 'web']);
        $roleNurse->givePermissionTo([
             'view_residents', 'administer_medications', 'manage_medications', 'view_reports'
        ]);

        // STAFF GENERAL: solo consulta
        $roleStaff = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);
        $roleStaff->givePermissionTo(['view_residents']);

        // Si la base ya tenía el rol Doctor sembrado, se retira para dejar solo 3 perfiles.
        // Los usuarios que lo tuvieran asignado pasan a Enfermera, que es el rol equivalente.
        $roleDoctor = Role::where('name', 'Doctor')->where('guard_name', 'web')->first();
        if ($roleDoctor) {
            foreach ($roleDoctor->users as $user) {
                $user->syncRoles(['Enfermera']);
                $user->update(['role' => 'Enfermera']);
            }
            $roleDoctor->delete();
        }
    }
}
