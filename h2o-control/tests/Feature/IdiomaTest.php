<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdiomaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_aplicacion_esta_en_espanol_y_hora_de_bolivia(): void
    {
        $this->assertSame('es', app()->getLocale());
        $this->assertSame('America/La_Paz', config('app.timezone'));
    }

    public function test_los_errores_de_validacion_salen_en_espanol_con_nombres_legibles(): void
    {
        $this->seed(RolSeeder::class);
        $superadmin = User::factory()->create(['rol_id' => RolUsuario::SuperAdmin->value]);

        $this->actingAs($superadmin)->post('/usuarios', ['ci' => str_repeat('9', 30)])
            ->assertSessionHasErrors([
                'name' => 'El campo nombre es obligatorio.',
                'email' => 'El campo correo electrónico es obligatorio.',
                'ci' => 'El campo carnet de identidad no debe tener más de 20 caracteres.',
            ]);
    }

    public function test_login_fallido_en_espanol(): void
    {
        $this->post('/login', ['email' => 'nadie@otb.com', 'password' => 'x'])
            ->assertSessionHasErrors(['email' => 'El correo o la contraseña son incorrectos.']);
    }

    public function test_menu_y_paginacion_en_espanol(): void
    {
        $this->seed(RolSeeder::class);
        $superadmin = User::factory()->create(['rol_id' => RolUsuario::SuperAdmin->value]);
        User::factory()->count(25)->create(['rol_id' => RolUsuario::Socio->value]);

        $this->actingAs($superadmin)->get('/usuarios')
            ->assertSee('Cerrar Sesión')
            ->assertSee('Mostrando')
            ->assertSee('resultados')
            ->assertDontSee('Showing');
    }
}
