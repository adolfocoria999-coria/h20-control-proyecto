<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
    }

    private function usuarioConRol(?RolUsuario $rol): User
    {
        return User::factory()->create(['rol_id' => $rol?->value]);
    }

    public function test_socio_no_puede_gestionar_usuarios(): void
    {
        $socio = $this->usuarioConRol(RolUsuario::Socio);

        $this->actingAs($socio)->get('/usuarios')->assertForbidden();
        $this->actingAs($socio)->get('/usuarios/exportar')->assertForbidden();
        $this->actingAs($socio)->post('/usuarios', [
            'name' => 'Intruso',
            'email' => 'intruso@test.com',
            'password' => 'secreto123',
            'rol_id' => RolUsuario::SuperAdmin->value,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'intruso@test.com']);
    }

    public function test_admin_no_puede_gestionar_usuarios(): void
    {
        $admin = $this->usuarioConRol(RolUsuario::Admin);

        $this->actingAs($admin)->get('/usuarios')->assertForbidden();
    }

    public function test_socio_no_accede_a_modulos_de_gestion(): void
    {
        $socio = $this->usuarioConRol(RolUsuario::Socio);

        foreach (['/lecturas', '/lecturas/crear', '/lecturas/exportar', '/reportes', '/reportes/exportar', '/finanzas/crear', '/multas/crear'] as $url) {
            $this->actingAs($socio)->get($url)->assertForbidden();
        }
    }

    public function test_usuario_sin_rol_no_es_admin(): void
    {
        $sinRol = $this->usuarioConRol(null);

        $this->actingAs($sinRol)->get('/lecturas')->assertForbidden();
        $this->actingAs($sinRol)->get('/dashboard')->assertRedirect(route('socio.consumo'));
    }

    public function test_el_email_no_otorga_privilegios(): void
    {
        $impostor = User::factory()->create([
            'name' => 'Adolfo Coria',
            'email' => 'adolfo@example.com',
            'rol_id' => RolUsuario::Socio->value,
        ]);

        $this->actingAs($impostor)->get('/usuarios')->assertForbidden();
        $this->actingAs($impostor)->get('/dashboard')->assertRedirect(route('socio.consumo'));
    }

    public function test_admin_accede_a_gestion(): void
    {
        $admin = $this->usuarioConRol(RolUsuario::Admin);

        $this->actingAs($admin)->get('/lecturas')->assertOk();
        $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('lecturas.index'));
    }

    public function test_superadmin_accede_a_usuarios(): void
    {
        $superadmin = $this->usuarioConRol(RolUsuario::SuperAdmin);

        $this->actingAs($superadmin)->get('/usuarios')->assertOk();
        $this->actingAs($superadmin)->get('/dashboard')->assertRedirect(route('usuarios.index'));
    }

    public function test_rol_inexistente_es_rechazado(): void
    {
        $superadmin = $this->usuarioConRol(RolUsuario::SuperAdmin);

        $this->actingAs($superadmin)->post('/usuarios', [
            'name' => 'Nuevo',
            'email' => 'nuevo@test.com',
            'password' => 'secreto123',
            'rol_id' => 99,
        ])->assertSessionHasErrors('rol_id');
    }

    public function test_superadmin_no_puede_cambiar_su_propio_rol(): void
    {
        $superadmin = $this->usuarioConRol(RolUsuario::SuperAdmin);

        $this->actingAs($superadmin)->put("/usuarios/{$superadmin->id}", [
            'name' => $superadmin->name,
            'email' => $superadmin->email,
            'rol_id' => RolUsuario::Socio->value,
        ]);

        $this->assertSame(RolUsuario::SuperAdmin->value, (int) $superadmin->fresh()->rol_id);
    }
}
