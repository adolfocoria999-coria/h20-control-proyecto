<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\TarifaMulta;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_los_seeders_se_pueden_ejecutar_en_cada_despliegue(): void
    {
        config(['otb.admin' => ['nombre' => 'Presidente', 'email' => 'presidente@otb.com', 'password' => 'una-clave-larga-y-segura']]);

        $this->seed();
        $this->seed();

        $this->assertSame(3, DB::table('rols')->count());
        $this->assertSame(3, TarifaMulta::count());
        $this->assertSame(1, User::count());
    }

    public function test_migrate_crea_roles_y_tarifas_sin_ejecutar_seeders(): void
    {
        // RefreshDatabase ya ejecutó todas las migraciones
        $this->assertSame(3, DB::table('rols')->count());
        $this->assertSame(3, TarifaMulta::count());
        $this->assertSame(0, User::count()); // sin ADMIN_EMAIL no crea superadmin
    }

    public function test_la_migracion_crea_el_superadmin_si_esta_configurado(): void
    {
        config(['otb.admin' => ['nombre' => 'Presidente', 'email' => 'presidente@otb.com', 'password' => 'una-clave-larga-y-segura']]);

        $migracion = require database_path('migrations/2026_10_07_000000_cargar_datos_iniciales.php');
        $migracion->up();
        $migracion->up();

        $this->assertTrue(User::sole()->esSuperAdmin());
    }
}
