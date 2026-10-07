<?php

namespace Database\Seeders;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Crea el superadministrador inicial con los datos de .env:
 * ADMIN_NOMBRE, ADMIN_EMAIL y ADMIN_PASSWORD (mínimo 10 caracteres).
 * Si ya existe un usuario con ese correo, no hace nada.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('otb.admin.email');
        $password = (string) config('otb.admin.password');

        if (! $email || strlen($password) < 10) {
            throw new RuntimeException('Define ADMIN_EMAIL y ADMIN_PASSWORD (mínimo 10 caracteres) en .env antes de ejecutar los seeders.');
        }

        // La contraseña se cifra sola (cast "hashed" del modelo)
        User::firstOrCreate(['email' => $email], [
            'name' => config('otb.admin.nombre'),
            'password' => $password,
            'rol_id' => RolUsuario::SuperAdmin->value,
        ]);
    }
}
