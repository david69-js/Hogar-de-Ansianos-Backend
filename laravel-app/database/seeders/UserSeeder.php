<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@hogar.com'],
            [
                'first_name' => 'Super',
                'last_name'  => 'Administrador',
                'dpi'        => '0000000000000',
                'phone'      => '00000000',
                'password'   => Hash::make('password123'),
                // Correo personal real al que llega el código de recuperación: el
                // institucional (@hogar.com) no es un buzón que exista. Sin esto,
                // después de cada migrate:fresh el admin se queda sin forma de
                // recuperar su contraseña (el correo se manda a @hogar.com y el
                // proveedor lo rechaza). Se define con ADMIN_RECOVERY_EMAIL.
                'recovery_email' => env('ADMIN_RECOVERY_EMAIL'),
                'role'       => 'Admin',
                'status'     => 'active',
            ]
        );

        $admin->syncRoles(['Admin']);
    }
}
