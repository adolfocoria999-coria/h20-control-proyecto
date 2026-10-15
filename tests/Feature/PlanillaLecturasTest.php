<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Ajuste;
use App\Models\Lectura;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanillaLecturasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $ana;

    private User $beto;

    private User $nuevo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        Ajuste::guardar(Ajuste::TARIFA_M3, '2.00');
        $this->admin = User::factory()->create(['rol_id' => RolUsuario::Admin->value, 'name' => 'Hacienda']);
        $socio = fn (string $nombre) => User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => $nombre]);
        $this->ana = $socio('Ana Quispe');
        $this->beto = $socio('Beto Mamani');
        $this->nuevo = $socio('Ceci Nueva');

        // Septiembre: Ana y Beto ya tienen lectura; Ceci nunca tuvo
        $this->lectura($this->ana, 9, 100, 110);
        $this->lectura($this->beto, 9, 500, 520);
    }

    private function lectura(User $socio, int $mes, float $anterior, float $actual): Lectura
    {
        return Lectura::factory()->for($socio, 'usuario')
            ->create(['mes' => $mes, 'gestion' => 2026, 'lectura_anterior' => $anterior, 'lectura_actual' => $actual]);
    }

    private function guardar(array $lecturas, int $mes = 10)
    {
        return $this->actingAs($this->admin)->from("/lecturas/planilla?mes={$mes}&gestion=2026")
            ->post('/lecturas/planilla', ['mes' => $mes, 'gestion' => 2026, 'lecturas' => $lecturas]);
    }

    public function test_la_planilla_muestra_a_todos_con_su_lectura_anterior(): void
    {
        $this->actingAs($this->admin)->get('/lecturas/planilla?mes=10&gestion=2026')
            ->assertOk()
            ->assertSee('Carga rápida – Octubre 2026')
            ->assertSeeInOrder(['Ana Quispe', '110 m³', 'Beto Mamani', '520 m³', 'Ceci Nueva'])
            ->assertSee('name="lecturas['.$this->nuevo->id.'][anterior]"', false)        // Ceci: lectura inicial
            ->assertDontSee('name="lecturas['.$this->ana->id.'][anterior]"', false);     // Ana: la pone el sistema

        $this->actingAs($this->admin)->get('/lecturas')->assertSee(route('lecturas.planilla'));
    }

    public function test_guarda_varias_lecturas_de_una_vez_con_la_anterior_del_sistema(): void
    {
        $this->guardar([
            $this->ana->id => ['actual' => 125, 'anterior' => 0],   // la anterior enviada se ignora
            $this->beto->id => ['actual' => ''],                     // todavía no se visitó
            $this->nuevo->id => ['actual' => 1008, 'anterior' => 1000],
        ])->assertSessionHasNoErrors()->assertSessionHas('success', '2 lectura(s) de Octubre 2026 registrada(s) ✓');

        $ana = Lectura::where(['user_id' => $this->ana->id, 'mes' => 10])->sole();
        $this->assertEquals(110, $ana->lectura_anterior);
        $this->assertEquals(15, $ana->consumo);
        $this->assertEquals(30, $ana->monto);
        $this->assertEquals(8, Lectura::where(['user_id' => $this->nuevo->id, 'mes' => 10])->sole()->consumo);
        $this->assertFalse(Lectura::where(['user_id' => $this->beto->id, 'mes' => 10])->exists());

        // Las ya registradas aparecen como hechas y cuentan en el avance
        $this->actingAs($this->admin)->get('/lecturas/planilla?mes=10&gestion=2026')
            ->assertViewHas('registradas', 2)
            ->assertSee('✅ Registrada');
    }

    public function test_si_una_fila_tiene_error_no_se_guarda_ninguna_y_se_conserva_lo_escrito(): void
    {
        $this->guardar([
            $this->ana->id => ['actual' => 125],
            $this->beto->id => ['actual' => 515],     // menor que 520
            $this->nuevo->id => ['actual' => 1008],   // falta la inicial
        ])->assertSessionHasErrors([
            "lecturas.{$this->beto->id}.actual" => 'Beto Mamani: la lectura actual no puede ser menor que la anterior (520 m³).',
            "lecturas.{$this->nuevo->id}.anterior" => 'Ceci Nueva: es su primera lectura, escribe la lectura inicial del medidor.',
        ]);

        $this->assertSame(2, Lectura::count());
        $this->actingAs($this->admin)->get('/lecturas/planilla?mes=10&gestion=2026')
            ->assertSee('value="125"', false)
            ->assertSee('Beto Mamani: la lectura actual no puede ser menor');
    }

    public function test_no_pisa_un_mes_ya_registrado_ni_acepta_texto(): void
    {
        $this->lectura($this->ana, 10, 110, 118);

        $this->guardar([$this->ana->id => ['actual' => 999]])
            ->assertSessionHas('error', 'No escribiste ninguna lectura nueva.');
        $this->assertEquals(118, Lectura::where(['user_id' => $this->ana->id, 'mes' => 10])->sole()->lectura_actual);

        $this->guardar([$this->beto->id => ['actual' => 'abc']])
            ->assertSessionHasErrors("lecturas.{$this->beto->id}.actual");
    }

    public function test_un_mes_atrasado_respeta_al_mes_siguiente(): void
    {
        // Agosto atrasado para Ana: no puede superar septiembre (110) y septiembre se encadena
        $this->guardar([$this->ana->id => ['actual' => 130, 'anterior' => 90]], mes: 8)
            ->assertSessionHasErrors("lecturas.{$this->ana->id}.actual");

        $this->guardar([$this->ana->id => ['actual' => 104, 'anterior' => 90]], mes: 8)->assertSessionHasNoErrors();
        $this->assertEquals(104, Lectura::where(['user_id' => $this->ana->id, 'mes' => 9])->sole()->lectura_anterior);
    }

    public function test_solo_hacienda_y_superadmin_usan_la_planilla(): void
    {
        $this->actingAs($this->ana)->get('/lecturas/planilla')->assertForbidden();
        $this->actingAs($this->ana)->post('/lecturas/planilla', ['mes' => 10, 'gestion' => 2026])->assertForbidden();
    }
}
