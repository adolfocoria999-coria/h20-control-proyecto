<?php

namespace Database\Factories;

use App\Models\Actividad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Actividad>
 */
class ActividadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'usuario_nombre' => fake()->name(),
            'rol' => 'admin',
            'seccion' => Actividad::SECCION_ADMIN,
            'accion' => 'creado',
            'modulo' => 'lecturas',
            'descripcion' => 'Registró una lectura de prueba',
            'ip' => '127.0.0.1',
        ];
    }

    public function deUsuario(): static
    {
        return $this->state(['rol' => 'socio', 'seccion' => Actividad::SECCION_USUARIO, 'modulo' => 'usuarios', 'accion' => 'actualizado', 'descripcion' => 'Actualizó su perfil']);
    }

    public function enFecha(string $fecha): static
    {
        return $this->state(['created_at' => $fecha]);
    }
}
