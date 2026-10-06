<?php

namespace Database\Factories;

use App\Models\Lectura;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lectura>
 */
class LecturaFactory extends Factory
{
    public function definition(): array
    {
        $anterior = fake()->randomFloat(2, 0, 500);

        return [
            'user_id' => User::factory(),
            'mes' => fake()->numberBetween(1, 12),
            'gestion' => (int) date('Y'),
            'lectura_anterior' => $anterior,
            'lectura_actual' => $anterior + fake()->randomFloat(2, 0, 50),
            'estado' => Lectura::PENDIENTE,
        ];
    }

    public function pagada(): static
    {
        return $this->state(['estado' => Lectura::PAGADO]);
    }
}
