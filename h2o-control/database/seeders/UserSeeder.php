<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Adolfo Coria',
            'email' => 'admin@otb.com',
            'password' => Hash::make('12345678'),
            'rol_id' => 1, // 1 = Superadministrador
            'ci' => '12345678',
            'telefono' => '77777777',
        ]);
    }
}
