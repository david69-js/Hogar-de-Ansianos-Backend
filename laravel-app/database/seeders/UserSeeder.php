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
                'role'       => 'Admin',
                'status'     => 'active',
            ]
        );

        $admin->syncRoles(['Admin']);
    }
}
