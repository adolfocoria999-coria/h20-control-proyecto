<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Http\Controllers\LecturaController;
use App\Http\Controllers\UserController;
use App\Models\Lectura;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginacionTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        $this->superadmin = User::factory()->create(['rol_id' => RolUsuario::SuperAdmin->value, 'name' => 'Zz Admin']);
    }

    public function test_lecturas_se_paginan_y_la_mas_reciente_va_primero(): void
    {
        // Cada lectura de un socio distinto: solo puede haber una por socio y periodo
        Lectura::factory()->count(LecturaController::POR_PAGINA + 5)->create(['gestion' => 2025]);
        $reciente = Lectura::factory()->create(['gestion' => 2026, 'mes' => 12]);

        $this->actingAs($this->superadmin)->get('/lecturas')
            ->assertViewHas('lecturas', fn ($p) => $p->total() === LecturaController::POR_PAGINA + 6
                && $p->count() === LecturaController::POR_PAGINA
                && $p->first()->is($reciente));

        $this->actingAs($this->superadmin)->get('/lecturas?page=2')
            ->assertViewHas('lecturas', fn ($p) => $p->count() === 6);
    }

    public function test_filtros_de_lecturas_y_totales_generales(): void
    {
        $ana = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Ana Quispe', 'ci' => '555111']);
        $luis = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Luis Mamani']);

        Lectura::factory()->for($ana, 'usuario')->create(['mes' => 3, 'gestion' => 2026, 'lectura_anterior' => 0, 'lectura_actual' => 10]);
        Lectura::factory()->for($luis, 'usuario')->create(['mes' => 4, 'gestion' => 2026, 'lectura_anterior' => 0, 'lectura_actual' => 5]);
        Lectura::factory()->for($luis, 'usuario')->pagada()->create(['mes' => 3, 'gestion' => 2025, 'lectura_anterior' => 0, 'lectura_actual' => 1]);

        $soloIds = fn (string $url) => $this->actingAs($this->superadmin)->get($url)->viewData('lecturas')->pluck('user_id')->all();

        $this->assertSame([$ana->id], $soloIds('/lecturas?socio=quispe'));
        $this->assertSame([$ana->id], $soloIds('/lecturas?socio=555111'));
        $this->assertCount(2, $soloIds('/lecturas?mes=3'));
        $this->assertSame([$luis->id], $soloIds('/lecturas?estado=pagado'));
        $this->assertSame([$ana->id], $soloIds('/lecturas?mes=3&gestion=2026'));

        // Las tarjetas muestran totales generales (15 m³ pendientes * Bs. 2), sin importar el filtro
        $this->actingAs($this->superadmin)->get('/lecturas?socio=quispe')
            ->assertViewHas('totalPendiente', 30.0)
            ->assertViewHas('totalCobrado', 2.0)
            ->assertViewHas('cantPendientes', 2);
    }

    public function test_los_enlaces_de_pagina_conservan_los_filtros(): void
    {
        Lectura::factory()->count(LecturaController::POR_PAGINA + 1)->create(['estado' => Lectura::PENDIENTE]);

        $this->actingAs($this->superadmin)->get('/lecturas?estado=pendiente')
            ->assertSee('estado=pendiente&amp;page=2', false);
    }

    // La tabla debe cerrarse bien y no mostrar restos de texto antes de la paginación
    public function test_las_tablas_se_cierran_correctamente(): void
    {
        Lectura::factory()->create();

        foreach (['/lecturas', '/usuarios'] as $url) {
            $html = $this->actingAs($this->superadmin)->get($url)->assertOk()->getContent();

            $this->assertStringNotContainsString('\1', $html, $url);
            $this->assertSame(substr_count($html, '<table'), substr_count($html, '</table>'), $url);
        }
    }

    public function test_usuarios_se_paginan_y_filtran(): void
    {
        User::factory()->count(UserController::POR_PAGINA + 3)->create(['rol_id' => RolUsuario::Socio->value]);
        $buscado = User::factory()->create([
            'rol_id' => RolUsuario::Socio->value, 'name' => 'Rosa Condori', 'ci' => '7777123', 'telefono' => '70011223',
        ]);

        $this->actingAs($this->superadmin)->get('/usuarios')
            ->assertViewHas('usuarios', fn ($p) => $p->total() === UserController::POR_PAGINA + 5
                && $p->count() === UserController::POR_PAGINA);

        foreach (['nombre=condori', 'ci=77771', 'telefono=0011223'] as $filtro) {
            $this->actingAs($this->superadmin)->get("/usuarios?{$filtro}")
                ->assertViewHas('usuarios', fn ($p) => $p->total() === 1 && $p->first()->is($buscado));
        }
    }
}
