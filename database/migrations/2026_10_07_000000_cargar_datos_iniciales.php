<?php

use App\Enums\RolUsuario;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Database\Seeders\TarifaMultaSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Datos que el sistema necesita para funcionar: roles, tarifas de multas y el
 * superadministrador inicial. Va como migración para que el hosting los cree
 * con `php artisan migrate`, sin tener que ejecutar los seeders a mano.
 * Todo es idempotente: en una base que ya tiene datos no duplica nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new RolSeeder)->run();
        (new TarifaMultaSeeder)->run();

        // El superadmin solo se crea si se configuró ADMIN_EMAIL y aún no hay ninguno
        $haySuperadmin = User::withTrashed()->where('rol_id', RolUsuario::SuperAdmin->value)->exists();

        if (config('otb.admin.email') && ! $haySuperadmin) {
            (new UserSeeder)->run();
        }
    }

    public function down(): void
    {
        // Datos de referencia: no se borran al revertir
    }
};
