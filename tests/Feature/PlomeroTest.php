<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Lectura;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlomeroTest extends TestCase
{
    use RefreshDatabase;

    private User $plomero;

    private User $admin;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        $this->plomero = User::factory()->create(['rol_id' => RolUsuario::Plomero->value, 'name' => 'Carlos Plomero']);
        $this->admin = User::factory()->create(['rol_id' => RolUsuario::Admin->value]);
        $this->superadmin = User::factory()->create(['rol_id' => RolUsuario::SuperAdmin->value]);
    }

    private function socio(string $nombre, ?string $zona = null, ?int $orden = null): User
    {
        return User::factory()->create([
            'rol_id' => RolUsuario::Socio->value, 'name' => $nombre, 'zona' => $zona, 'orden_recorrido' => $orden,
        ]);
    }

    public function test_el_rol_plomero_existe_y_se_puede_asignar(): void
    {
        $this->assertDatabaseHas('rols', ['id' => 4, 'nombre' => 'Plomero']);

        $this->actingAs($this->superadmin)->get('/usuarios/crear')->assertSee('Plomero');
        $this->actingAs($this->superadmin)->post('/usuarios', [
            'name' => 'Pedro Plomero', 'email' => 'pedro@otb.test', 'password' => 'Temporal123',
            'rol_id' => RolUsuario::Plomero->value,
        ])->assertSessionHasNoErrors();

        $this->assertTrue(User::where('email', 'pedro@otb.test')->sole()->esPlomero());
    }

    public function test_el_plomero_entra_directo_a_la_planilla_y_carga_lecturas(): void
    {
        $socio = $this->socio('Ana Quispe');
        Lectura::factory()->for($socio, 'usuario')->create(['mes' => 9, 'gestion' => 2026, 'lectura_anterior' => 100, 'lectura_actual' => 110]);

        $this->post('/login', ['email' => $this->plomero->email, 'password' => 'password'])
            ->assertRedirect(route('lecturas.planilla'));
        $this->get('/dashboard')->assertRedirect(route('lecturas.planilla'));

        $this->get('/lecturas/planilla?mes=10&gestion=2026')->assertOk()
            ->assertSee('Carga de Lecturas')
            ->assertDontSee('Volver a Lecturas')
            ->assertDontSee(route('reportes.index'));

        $this->post('/lecturas/planilla', ['mes' => 10, 'gestion' => 2026, 'lecturas' => [$socio->id => ['actual' => 118]]])
            ->assertSessionHasNoErrors();
        $this->assertEquals(8, Lectura::where(['user_id' => $socio->id, 'mes' => 10])->sole()->consumo);

        // Ya registrada: la ve como hecha, pero corregirla es tarea de Hacienda
        $this->get('/lecturas/planilla?mes=10&gestion=2026')
            ->assertSee('✅ Registrada')
            ->assertDontSee('/editar');
    }

    public function test_el_plomero_no_entra_a_cobros_ni_a_la_gestion(): void
    {
        $socio = $this->socio('Ana Quispe');
        $lectura = Lectura::factory()->for($socio, 'usuario')->create();

        foreach (['/lecturas', '/lecturas/crear', "/lecturas/{$lectura->id}/editar", '/usuarios', '/reportes',
            '/historial', '/finanzas/arqueo', '/tarifa-agua', '/finanzas/crear', '/multas/crear'] as $url) {
            $this->actingAs($this->plomero)->get($url)->assertForbidden();
        }

        $this->actingAs($this->plomero)->patch("/lecturas/{$lectura->id}/pagar", ['metodo_pago' => 'qr'])->assertForbidden();
        $this->actingAs($this->plomero)->delete("/lecturas/{$lectura->id}")->assertForbidden();
        $this->assertSame(Lectura::PENDIENTE, $lectura->fresh()->estado);
    }

    public function test_el_plomero_no_puede_ver_recibos_de_los_socios(): void
    {
        $socio = $this->socio('Ana Quispe');
        $lectura = Lectura::factory()->for($socio, 'usuario')->create();
        $this->actingAs($this->admin)->patch("/lecturas/{$lectura->id}/pagar", ['metodo_pago' => 'efectivo']);

        $this->actingAs($this->plomero)->get("/recibos/agua/{$lectura->id}")->assertForbidden();
        $this->actingAs($this->admin)->get("/recibos/agua/{$lectura->id}")->assertOk();
    }

    public function test_los_socios_no_usan_la_planilla(): void
    {
        $socio = $this->socio('Ana Quispe');

        $this->actingAs($socio)->get('/lecturas/planilla')->assertForbidden();
    }

    public function test_la_planilla_sigue_el_orden_del_recorrido(): void
    {
        $this->socio('Zulema Arce', 'Zona Norte', 2);
        $this->socio('Beto Mamani', 'Zona Norte', 1);
        $this->socio('Ana Quispe', 'Zona Sur', 1);
        $this->socio('Carla Sin Zona');

        $this->actingAs($this->plomero)->get('/lecturas/planilla?mes=10&gestion=2026')
            ->assertSeeInOrder(['📍 Zona Norte', 'Beto Mamani', 'Zulema Arce', '📍 Zona Sur', 'Ana Quispe', '📍 Sin zona', 'Carla Sin Zona'])
            ->assertSee('N.º 1')
            ->assertSee('<option value="Zona Norte">', false);
    }

    public function test_zona_y_orden_se_guardan_en_la_ficha_del_socio(): void
    {
        $this->actingAs($this->superadmin)->post('/usuarios', [
            'name' => 'Rosa Condori', 'email' => 'rosa@otb.test', 'password' => 'Temporal123',
            'rol_id' => RolUsuario::Socio->value, 'zona' => '  Zona   Norte ', 'orden_recorrido' => 7,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'rosa@otb.test', 'zona' => 'Zona Norte', 'orden_recorrido' => 7]);

        $this->actingAs($this->superadmin)->post('/usuarios', [
            'name' => 'Otra Persona', 'email' => 'otra@otb.test', 'password' => 'Temporal123',
            'rol_id' => RolUsuario::Socio->value, 'orden_recorrido' => 0,
        ])->assertSessionHasErrors('orden_recorrido');

        $this->actingAs($this->superadmin)->get('/usuarios')->assertSee('📍 Zona Norte · N.º 7');
    }
}
