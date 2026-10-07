<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * Preparación para publicar en un hosting.
 */
class ProduccionTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_superadmin_inicial_exige_una_contrasena_segura(): void
    {
        $this->seed(RolSeeder::class);
        config(['otb.admin' => ['nombre' => 'Presidente', 'email' => 'presidente@otb.com', 'password' => '12345678']]);

        $this->expectException(RuntimeException::class);
        $this->seed(UserSeeder::class);
    }

    public function test_el_superadmin_inicial_se_crea_una_sola_vez_desde_la_configuracion(): void
    {
        $this->seed(RolSeeder::class);
        config(['otb.admin' => ['nombre' => 'Presidente', 'email' => 'presidente@otb.com', 'password' => 'una-clave-larga-y-segura']]);

        $this->seed(UserSeeder::class);
        $this->seed(UserSeeder::class);

        $admin = User::sole();
        $this->assertSame('Presidente', $admin->name);
        $this->assertTrue($admin->esSuperAdmin());
        $this->assertSame(RolUsuario::SuperAdmin->value, (int) $admin->rol_id);
        $this->assertTrue(Hash::check('una-clave-larga-y-segura', $admin->password));
    }

    public function test_detras_del_proxy_https_las_urls_salen_con_https(): void
    {
        $this->get('/login', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'h2o.ejemplo.app'])
            ->assertOk()
            ->assertSee('action="https://h2o.ejemplo.app/login"', false);
    }
}
