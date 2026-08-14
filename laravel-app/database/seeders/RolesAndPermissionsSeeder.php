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
        // ADMINISTRADOR: Todo
        $roleAdmin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $roleAdmin->givePermissionTo(Permission::all());

        // DOCTOR
        $roleDoctor = Role::firstOrCreate(['name' => 'Doctor', 'guard_name' => 'web']);
        $roleDoctor->givePermissionTo([
            'view_residents', 'create_residents', 'edit_residents', 'manage_medications', 'administer_medications', 'view_reports'
        ]);

        // ENFERMERA
        // Aunque solo puede ver residentes (no crearlos/editarlos), sí necesita poder
        // marcar medicamentos como administrados/no administrados: es su tarea principal.
        $roleNurse = Role::firstOrCreate(['name' => 'Enfermera', 'guard_name' => 'web']);
        $roleNurse->givePermissionTo([
             'view_residents', 'administer_medications', 'view_reports'
        ]);
        
        // STAFF GENERAL
        $roleStaff = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);
        $roleStaff->givePermissionTo(['view_residents']);

    }
}
