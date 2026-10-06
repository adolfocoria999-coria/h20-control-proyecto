<?php

namespace Database\Seeders;

use App\Models\TarifaMulta;
use Illuminate\Database\Seeder;

class TarifaMultaSeeder extends Seeder
{
    public function run(): void
    {
        $tarifas = [
            'Inasistencia a Desfile Cívico' => 50.00,
            'Inasistencia a Reunión / Asamblea' => 30.00,
            'Inasistencia a Trabajo Comunal (Faena/Ayni)' => 80.00,
        ];

        // Idempotente: se puede ejecutar varias veces sin duplicar ni borrar datos
        foreach ($tarifas as $nombre => $monto) {
            TarifaMulta::updateOrCreate(['nombre' => $nombre], ['monto_predeterminado' => $monto]);
        }
    }
}
