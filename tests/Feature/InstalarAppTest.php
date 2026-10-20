<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstalarAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
    }

    public function test_todos_los_roles_pueden_instalar_la_app_desde_el_menu(): void
    {
        foreach ([RolUsuario::Socio->value => '/mi-consumo', RolUsuario::Plomero->value => '/lecturas/planilla', RolUsuario::Admin->value => '/lecturas', RolUsuario::SuperAdmin->value => '/usuarios'] as $rol => $url) {
            $usuario = User::factory()->create(['rol_id' => $rol]);

            $this->actingAs($usuario)->get($url)->assertOk()
                ->assertSee('id="aviso-instalar"', false)          // aviso "Instala H2O Control..."
                ->assertSee('📲 Instalar app')                     // opción fija del menú (reinstalar si se borró)
                ->assertSee('id="ayuda-instalar"', false)          // pasos para Android y iPhone
                ->assertSee('Agregar a inicio');
        }
    }

    public function test_tambien_se_puede_instalar_desde_el_login(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('id="aviso-instalar"', false)
            ->assertSee("addEventListener('beforeinstallprompt'", false);
    }
}
