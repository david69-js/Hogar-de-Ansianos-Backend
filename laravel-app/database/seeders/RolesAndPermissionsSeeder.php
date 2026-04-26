<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
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
            'view_reports',
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
            'view_residents', 'create_residents', 'edit_residents', 'manage_medications', 'view_reports'
        ]);

        // ENFERMERA
        $roleNurse = Role::firstOrCreate(['name' => 'Enfermera', 'guard_name' => 'web']);
        $roleNurse->givePermissionTo([
             'view_residents', 'view_reports'
        ]);
        
        // STAFF GENERAL
        $roleStaff = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);
        $roleStaff->givePermissionTo(['view_residents']);

        // 3. Crear Usuario Administrador Base
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'first_name' => 'Super',
                'last_name' => 'Administrador',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'dpi' => '0000000000000',
                'phone' => '00000000',
                'role' => 'Admin'
            ]
        );
        $adminUser->assignRole('Admin');
    }
}
