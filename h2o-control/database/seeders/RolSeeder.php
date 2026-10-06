<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('rols')->insert([
            [
                'nombre' => 'Superadministrador',
                'descripcion' => 'Presidente de la OTB. Gestiona usuarios, roles y supervisa el sistema.',
            ],
            [
                'nombre' => 'Administrador',
                'descripcion' => 'Secretario de Hacienda. Registra pagos, consumos, socios y emite recibos.',
            ],
            [
                'nombre' => 'Socio Común',
                'descripcion' => 'Consulta historial de pagos, deudas pendientes y comprobantes.',
            ],
        ]);
    }
}
