<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClaveTemporalTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private User $socio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        $this->superadmin = User::factory()->create(['rol_id' => RolUsuario::SuperAdmin->value]);
        $this->socio = User::factory()->create([
            'rol_id' => RolUsuario::Socio->value, 'name' => 'Juan Pérez',
            'email' => 'juan@otb.test', 'telefono' => '71234567', 'password' => 'ClaveVieja123',
        ]);
    }

    private function restablecer(): array
    {
        $this->actingAs($this->superadmin)->post("/usuarios/{$this->socio->id}/restablecer-clave")
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('clave_temporal');

        return session('clave_temporal');
    }

    public function test_el_superadmin_genera_una_clave_temporal_y_la_ve_una_sola_vez(): void
    {
        $nueva = $this->restablecer();

        $this->assertMatchesRegularExpression('/^H2O-\d{4}-\d{4}$/', $nueva['clave']);
        $this->socio->refresh();
        $this->assertTrue(Hash::check($nueva['clave'], $this->socio->password));
        $this->assertFalse(Hash::check('ClaveVieja123', $this->socio->password));
        $this->assertTrue($this->socio->debe_cambiar_clave);

        // Enlace de WhatsApp con la clave para el celular del socio
        $this->assertStringStartsWith('https://wa.me/59171234567?text=', $nueva['whatsapp']);
        $this->assertStringContainsString(rawurlencode($nueva['clave']), $nueva['whatsapp']);

        $this->actingAs($this->superadmin)->get('/usuarios')->assertSee($nueva['clave']);
        $this->actingAs($this->superadmin)->get('/usuarios')->assertDontSee($nueva['clave']);

        $this->assertDatabaseHas('actividades', ['descripcion' => 'Restableció la contraseña de Juan Pérez']);
    }

    public function test_solo_el_superadmin_restablece_y_no_la_suya(): void
    {
        $admin = User::factory()->create(['rol_id' => RolUsuario::Admin->value]);

        $this->actingAs($admin)->post("/usuarios/{$this->socio->id}/restablecer-clave")->assertForbidden();
        $this->actingAs($this->socio)->post("/usuarios/{$this->superadmin->id}/restablecer-clave")->assertForbidden();

        $this->actingAs($this->superadmin)->post("/usuarios/{$this->superadmin->id}/restablecer-clave")
            ->assertSessionHas('error');
        $this->assertFalse($this->superadmin->fresh()->debe_cambiar_clave);
    }

    public function test_con_clave_temporal_solo_puede_cambiarla_y_luego_usa_el_sistema(): void
    {
        $clave = $this->restablecer()['clave'];
        auth()->logout();

        $this->post('/login', ['email' => 'juan@otb.test', 'password' => 'ClaveVieja123'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'juan@otb.test', 'password' => $clave]);
        $this->assertAuthenticatedAs($this->socio->fresh());

        // Cualquier pantalla lo manda a cambiar la contraseña
        $this->get('/mi-consumo')->assertRedirect(route('profile.edit'));
        $this->get('/profile')->assertOk()->assertSee('Estás usando una contraseña temporal');

        $this->put('/password', [
            'current_password' => $clave,
            'password' => 'MiClaveNueva2026',
            'password_confirmation' => 'MiClaveNueva2026',
        ])->assertRedirect(route('socio.consumo'));

        $this->assertFalse($this->socio->fresh()->debe_cambiar_clave);
        $this->get('/mi-consumo')->assertOk();
    }

    public function test_un_socio_nuevo_tambien_debe_cambiar_la_clave_que_le_puso_el_superadmin(): void
    {
        $this->actingAs($this->superadmin)->post('/usuarios', [
            'name' => 'Rosa Quispe', 'email' => 'rosa@otb.test', 'password' => 'Temporal123',
            'rol_id' => RolUsuario::Socio->value,
        ])->assertSessionHasNoErrors();

        $this->assertTrue(User::where('email', 'rosa@otb.test')->value('debe_cambiar_clave'));
    }

    public function test_el_login_ya_no_ofrece_recuperar_por_correo(): void
    {
        $this->get('/login')->assertOk()
            ->assertDontSee('forgot-password')
            ->assertSee('Pídela a la directiva');
        $this->get('/forgot-password')->assertNotFound();
    }
}
