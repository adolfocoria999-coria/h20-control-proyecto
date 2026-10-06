<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\BalanceMensual;
use App\Models\Lectura;
use App\Models\Multa;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * En celulares las tablas se muestran como tarjetas (clase .tabla-responsiva en app.css):
 * cada celda necesita su data-label para mostrar la etiqueta de la columna.
 */
class ResponsivoTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_tablas_tienen_version_para_celular(): void
    {
        $this->seed(RolSeeder::class);
        $superadmin = User::factory()->create(['rol_id' => RolUsuario::SuperAdmin->value]);
        $socio = User::factory()->create(['rol_id' => RolUsuario::Socio->value]);
        Lectura::factory()->for($socio, 'usuario')->create(['gestion' => (int) date('Y')]);
        Multa::factory()->for($socio, 'socio')->create();
        BalanceMensual::factory()->create();

        $paginas = [
            [$superadmin, '/lecturas', 'Monto (Bs.)'],
            [$superadmin, '/usuarios', 'Carnet de Identidad'],
            [$superadmin, '/finanzas', 'Saldo Final'],
            [$superadmin, '/multas', 'Estado'],
            [$superadmin, '/reportes', 'Total Acumulado'],
            [$socio, '/mi-consumo', 'Total a Pagar'],
        ];

        foreach ($paginas as [$usuario, $url, $etiqueta]) {
            $html = $this->actingAs($usuario)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('class="tabla-responsiva', $html, $url);
            $this->assertStringContainsString('data-label="'.$etiqueta.'"', $html, $url);

            // Toda celda de datos tiene etiqueta (solo el mensaje de "sin resultados" usa colspan)
            preg_match_all('/<td\b[^>]*>/', $html, $celdas);
            $sinEtiqueta = array_filter($celdas[0], fn ($td) => ! str_contains($td, 'data-label') && ! str_contains($td, 'colspan'));
            $this->assertSame([], array_values($sinEtiqueta), $url);
        }
    }
}
