<?php

namespace Database\Factories;

use App\Models\Multa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Multa>
 */
class MultaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tipo_multa' => 'Inasistencia a Reunión / Asamblea',
            'monto' => 30,
            'fecha_multa' => now()->toDateString(),
            'estado' => Multa::PENDIENTE,
        ];
    }

    public function pagada(): static
    {
        return $this->state(['estado' => Multa::PAGADO, 'fecha_pago' => now()->toDateString()]);
    }
}
