<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Lectura;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Todos los módulos usan el mismo botón <x-boton-excel> y exportan lo que se ve en pantalla.
 */
class ExportarExcelTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        $this->superadmin = User::factory()->create(['rol_id' => RolUsuario::SuperAdmin->value]);
    }

    public function test_todos_los_modulos_usan_el_mismo_boton_y_conservan_los_filtros(): void
    {
        $paginas = [
            '/lecturas?estado=pendiente' => 'lecturas.exportar',
            '/usuarios?nombre=ana' => 'usuarios.exportar',
            '/finanzas?gestion=2026' => 'finanzas.exportar',
            '/multas?estado=pendiente' => 'multas.exportar',
            '/reportes?gestion=2026' => 'reportes.exportar',
            '/historial?gestion=2026' => 'historial.exportar',
        ];

        foreach ($paginas as $url => $ruta) {
            $query = [];
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            $this->actingAs($this->superadmin)->get($url)->assertOk()
                ->assertSee('Exportar a Excel')
                ->assertSee('href="'.e(route($ruta, $query)).'"', false)
                ->assertDontSee('.Excel');
        }
    }

    public function test_lecturas_exporta_solo_lo_filtrado(): void
    {
        $ana = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Ana Quispe']);
        $luis = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Luis Mamani']);
        Lectura::factory()->for($ana, 'usuario')->create();
        Lectura::factory()->for($luis, 'usuario')->create();

        $csv = $this->actingAs($this->superadmin)->get('/lecturas/exportar?socio=quispe')->streamedContent();

        $this->assertStringContainsString('Ana Quispe', $csv);
        $this->assertStringNotContainsString('Luis Mamani', $csv);
    }

    public function test_socios_exporta_solo_lo_filtrado(): void
    {
        User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Rosa Condori', 'ci' => '777']);
        User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Pedro Choque', 'ci' => '888']);

        $csv = $this->actingAs($this->superadmin)->get('/usuarios/exportar?ci=777')->streamedContent();

        $this->assertStringContainsString('Rosa Condori', $csv);
        $this->assertStringNotContainsString('Pedro Choque', $csv);
    }
}
