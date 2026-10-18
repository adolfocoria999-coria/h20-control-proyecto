<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Lectura;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObservacionesTest extends TestCase
{
    use RefreshDatabase;

    private User $plomero;

    private User $admin;

    private User $ana;

    private User $beto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        $this->plomero = User::factory()->create(['rol_id' => RolUsuario::Plomero->value]);
        $this->admin = User::factory()->create(['rol_id' => RolUsuario::Admin->value]);
        $this->ana = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Ana Quispe']);
        $this->beto = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Beto Mamani']);
        foreach ([[$this->ana, 110], [$this->beto, 520]] as [$socio, $actual]) {
            Lectura::factory()->for($socio, 'usuario')
                ->create(['mes' => 9, 'gestion' => 2026, 'lectura_anterior' => $actual - 10, 'lectura_actual' => $actual]);
        }
    }

    private function planilla(array $lecturas)
    {
        return $this->actingAs($this->plomero)->from('/lecturas/planilla?mes=10&gestion=2026')
            ->post('/lecturas/planilla', ['mes' => 10, 'gestion' => 2026, 'lecturas' => $lecturas]);
    }

    public function test_el_rol_se_llama_plomero(): void
    {
        $this->assertDatabaseHas('rols', ['id' => RolUsuario::Plomero->value, 'nombre' => 'Plomero']);
        $this->assertSame('Plomero', RolUsuario::etiqueta('plomero'));
        $this->assertTrue($this->plomero->esPlomero());
    }

    public function test_el_plomero_anota_la_lectura_con_su_observacion(): void
    {
        $this->actingAs($this->plomero)->get('/lecturas/planilla?mes=10&gestion=2026')
            ->assertSee('name="lecturas['.$this->ana->id.'][observacion]"', false)
            ->assertSee('<datalist id="observaciones-comunes">', false)
            ->assertSee('Posible fuga');

        $this->planilla([
            $this->ana->id => ['actual' => '125', 'observacion' => '  Posible   fuga en la llave de paso '],
            $this->beto->id => ['actual' => '530', 'observacion' => ''],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Posible fuga en la llave de paso', Lectura::where(['user_id' => $this->ana->id, 'mes' => 10])->sole()->observacion);
        $this->assertNull(Lectura::where(['user_id' => $this->beto->id, 'mes' => 10])->sole()->observacion);

        // La ve el plomero en la fila ya registrada y Hacienda en Lecturas y en el Excel
        $this->actingAs($this->plomero)->get('/lecturas/planilla?mes=10&gestion=2026')->assertSee('📝 Posible fuga en la llave de paso');
        $this->actingAs($this->admin)->get('/lecturas')->assertSee('📝 Posible fuga en la llave de paso');
        $this->assertStringContainsString('Posible fuga en la llave de paso', $this->actingAs($this->admin)->get('/lecturas/exportar')->streamedContent());
    }

    public function test_una_observacion_sin_lectura_pide_la_lectura(): void
    {
        $this->planilla([$this->ana->id => ['actual' => '', 'observacion' => 'Medidor dañado']])
            ->assertSessionHasErrors(["lecturas.{$this->ana->id}.actual" => 'Ana Quispe: anota también la lectura del medidor; la observación se guarda junto con ella.']);

        $this->assertSame(0, Lectura::where('mes', 10)->count());
    }

    public function test_la_observacion_no_puede_ser_muy_larga(): void
    {
        $this->planilla([$this->ana->id => ['actual' => '125', 'observacion' => str_repeat('a', 256)]])
            ->assertSessionHasErrors("lecturas.{$this->ana->id}.observacion");
    }

    public function test_hacienda_tambien_anota_y_corrige_observaciones(): void
    {
        $this->actingAs($this->admin)->post('/lecturas', [
            'user_id' => $this->ana->id, 'mes' => 10, 'gestion' => 2026,
            'lectura_anterior' => 0, 'lectura_actual' => 125, 'observacion' => 'Medidor cambiado',
        ])->assertSessionHasNoErrors();

        $lectura = Lectura::where(['user_id' => $this->ana->id, 'mes' => 10])->sole();
        $this->assertSame('Medidor cambiado', $lectura->observacion);

        $this->actingAs($this->admin)->get("/lecturas/{$lectura->id}/editar")->assertSee('value="Medidor cambiado"', false);
        $this->actingAs($this->admin)->put("/lecturas/{$lectura->id}", [
            'user_id' => $this->ana->id, 'mes' => 10, 'gestion' => 2026,
            'lectura_anterior' => 0, 'lectura_actual' => 125, 'observacion' => '',
        ])->assertSessionHasNoErrors();
        $this->assertNull($lectura->fresh()->observacion);
    }

    public function test_el_aviso_de_sin_coincidencias_empieza_oculto_tambien_en_el_celular(): void
    {
        // En el celular .tabla-responsiva tr fuerza display:block, por eso se oculta con style
        $this->actingAs($this->plomero)->get('/lecturas/planilla?mes=10&gestion=2026')
            ->assertSee('<tr id="sin-coincidencias" style="display: none">', false);
    }
}
