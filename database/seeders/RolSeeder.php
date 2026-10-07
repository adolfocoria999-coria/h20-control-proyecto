<?php

namespace Database\Seeders;

use App\Enums\RolUsuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Roles del sistema con IDs fijos (los usa el enum RolUsuario).
 * Idempotente: se puede ejecutar en cada despliegue sin duplicar roles.
 */
class RolSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            RolUsuario::SuperAdmin->value => ['Superadministrador', 'Presidente de la OTB. Gestiona usuarios, roles y supervisa el sistema.'],
            RolUsuario::Admin->value => ['Administrador', 'Secretario de Hacienda. Registra pagos, consumos, socios y emite recibos.'],
            RolUsuario::Socio->value => ['Socio Común', 'Consulta historial de pagos, deudas pendientes y comprobantes.'],
        ];

        foreach ($roles as $id => [$nombre, $descripcion]) {
            DB::table('rols')->updateOrInsert(['id' => $id], ['nombre' => $nombre, 'descripcion' => $descripcion]);
        }
    }
}
