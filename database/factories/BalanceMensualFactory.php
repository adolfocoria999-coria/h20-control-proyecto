<?php

namespace Database\Factories;

use App\Models\BalanceMensual;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BalanceMensual>
 */
class BalanceMensualFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mes' => fake()->numberBetween(1, 12),
            'gestion' => (int) date('Y'),
            'ingresos' => fake()->randomFloat(2, 0, 5000),
            'egresos' => fake()->randomFloat(2, 0, 3000),
        ];
    }
}
