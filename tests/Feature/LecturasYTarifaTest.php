<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Ajuste;
use App\Models\Lectura;
use App\Models\User;
use App\Services\ReporteFinanciero;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LecturasYTarifaTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private User $admin;

    private User $socio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        $this->superadmin = User::factory()->create(['rol_id' => RolUsuario::SuperAdmin->value]);
        $this->admin = User::factory()->create(['rol_id' => RolUsuario::Admin->value]);
        $this->socio = User::factory()->create(['rol_id' => RolUsuario::Socio->value]);
        Ajuste::guardar(Ajuste::TARIFA_M3, '2.00');
    }

    private function registrar(int $mes, float $actual, float $anterior = 0, int $gestion = 2026)
    {
        return $this->actingAs($this->admin)->post('/lecturas', [
            'user_id' => $this->socio->id, 'mes' => $mes, 'gestion' => $gestion,
            'lectura_anterior' => $anterior, 'lectura_actual' => $actual,
        ]);
    }

    private function lectura(int $mes, int $gestion = 2026): Lectura
    {
        return Lectura::where(['user_id' => $this->socio->id, 'mes' => $mes, 'gestion' => $gestion])->sole();
    }

    // ---------- Lectura anterior del medidor ----------

    public function test_la_primera_lectura_usa_la_lectura_inicial_del_medidor(): void
    {
        $this->registrar(8, 1512, anterior: 1500)->assertSessionHasNoErrors();

        $this->assertEquals(1500, $this->lectura(8)->lectura_anterior);
        $this->assertEquals(12, $this->lectura(8)->consumo);
    }

    public function test_la_lectura_anterior_la_pone_el_servidor_aunque_la_alteren(): void
    {
        $this->registrar(8, 1512, anterior: 1500);

        // Alguien cambia a mano la lectura anterior a 0 para inflar o bajar el consumo
        $this->registrar(9, 1530, anterior: 0)->assertSessionHasNoErrors();

        $this->assertEquals(1512, $this->lectura(9)->lectura_anterior);
        $this->assertEquals(18, $this->lectura(9)->consumo);
    }

    public function test_no_se_puede_registrar_una_lectura_menor_a_la_del_mes_anterior(): void
    {
        $this->registrar(8, 1512, anterior: 1500);

        $this->registrar(9, 1505, anterior: 0)
            ->assertSessionHasErrors(['lectura_actual' => 'La lectura actual no puede ser menor que la lectura anterior (1512 m³).']);
        $this->assertSame(1, Lectura::count());
    }

    public function test_la_anterior_es_la_del_mes_previo_no_la_ultima_cargada(): void
    {
        $this->registrar(6, 1500, anterior: 1490);
        $this->registrar(8, 1530);          // julio todavía no se cargó: parte de junio (1500)
        $this->assertEquals(1500, $this->lectura(8)->lectura_anterior);

        // Se carga julio tarde: parte de junio, y agosto (pendiente) se encadena a julio
        $this->registrar(7, 1512)->assertSessionHasNoErrors();
        $this->assertEquals(1500, $this->lectura(7)->lectura_anterior);
        $this->assertEquals(1512, $this->lectura(8)->lectura_anterior);
        $this->assertEquals(18, $this->lectura(8)->consumo);
    }

    public function test_un_mes_atrasado_no_puede_superar_al_siguiente(): void
    {
        $this->registrar(6, 1500, anterior: 1490);
        $this->registrar(8, 1530);

        $this->registrar(7, 1540)
            ->assertSessionHasErrors(['lectura_actual' => 'La lectura actual no puede ser mayor que la de Agosto / 2026 (1530 m³).']);
    }

    public function test_si_el_mes_siguiente_ya_se_cobro_no_se_rompe_su_lectura_anterior(): void
    {
        $this->registrar(6, 1500, anterior: 1490);
        $this->registrar(8, 1530);
        $this->actingAs($this->admin)->patch('/lecturas/'.$this->lectura(8)->id.'/pagar', ['metodo_pago' => 'qr']);

        $this->registrar(7, 1512)->assertSessionHasErrors('lectura_actual');
        $this->registrar(7, 1500)->assertSessionHasNoErrors(); // coincide con la de agosto: 0 m³ en julio
        $this->assertEquals(30, $this->lectura(8)->consumo);
    }

    public function test_al_corregir_una_lectura_el_mes_siguiente_pendiente_se_actualiza(): void
    {
        $this->registrar(8, 1512, anterior: 1500);
        $this->registrar(9, 1530);
        $agosto = $this->lectura(8);

        $this->actingAs($this->admin)->put("/lecturas/{$agosto->id}", [
            'user_id' => $this->socio->id, 'mes' => 8, 'gestion' => 2026,
            'lectura_anterior' => 1500, 'lectura_actual' => 1515,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(1515, $this->lectura(9)->lectura_anterior);
        $this->assertEquals(15, $this->lectura(9)->consumo);
    }

    public function test_el_formulario_recibe_la_lectura_del_mes_previo_segun_el_periodo(): void
    {
        $this->registrar(6, 1500, anterior: 1490);
        $this->registrar(8, 1530);

        $this->actingAs($this->admin)->getJson("/lecturas/ultima/{$this->socio->id}?mes=7&gestion=2026")
            ->assertJson([
                'tiene_lectura' => true,
                'lectura_actual' => '1500.00',
                'periodo' => 'Junio / 2026',
                'siguiente' => ['periodo' => 'Agosto / 2026', 'lectura_actual' => '1530.00'],
            ]);

        $this->actingAs($this->admin)->getJson("/lecturas/ultima/{$this->socio->id}?mes=5&gestion=2026")
            ->assertJson(['tiene_lectura' => false, 'siguiente' => ['periodo' => 'Junio / 2026']]);
    }

    // ---------- Tarifa por lectura y montos cobrados ----------

    public function test_cada_lectura_conserva_su_tarifa_y_lo_cobrado_no_cambia(): void
    {
        $this->registrar(8, 10, anterior: 0);           // 10 m³ × Bs. 2 = 20
        $this->registrar(9, 20);                        // 10 m³ × Bs. 2 = 20 (pendiente)
        $this->actingAs($this->admin)->patch('/lecturas/'.$this->lectura(8)->id.'/pagar', ['metodo_pago' => 'efectivo']);

        $this->actingAs($this->superadmin)->put('/tarifa-agua', ['tarifa_m3' => 2.5])->assertSessionHasNoErrors();
        $this->registrar(10, 30);                       // 10 m³ × Bs. 2,50 = 25

        $this->assertEquals(2, $this->lectura(8)->tarifa_m3);
        $this->assertEquals(20, $this->lectura(8)->monto);
        $this->assertEquals(20, $this->lectura(9)->monto);   // deuda emitida: conserva su precio
        $this->assertEquals(2.5, $this->lectura(10)->tarifa_m3);
        $this->assertEquals(25, $this->lectura(10)->monto);

        // Tarjetas de Lecturas y Reportes: cobrado = lo cobrado; pendiente = con la tarifa de cada lectura
        $this->actingAs($this->admin)->get('/lecturas')
            ->assertViewHas('totalCobrado', 20.0)
            ->assertViewHas('totalPendiente', 45.0);

        $totales = (new ReporteFinanciero(2026, null))->totales();
        $this->assertEquals(20, $totales['agua_cobrada']);
        $this->assertEquals(45, $totales['agua_pendiente']);
    }

    // ---------- Pantalla de la tarifa ----------

    public function test_solo_el_superadmin_cambia_la_tarifa_y_queda_en_el_historial(): void
    {
        $this->actingAs($this->admin)->get('/tarifa-agua')->assertForbidden();
        $this->actingAs($this->admin)->put('/tarifa-agua', ['tarifa_m3' => 9])->assertForbidden();
        $this->actingAs($this->socio)->get('/tarifa-agua')->assertForbidden();

        $this->actingAs($this->superadmin)->get('/tarifa-agua')->assertOk()->assertSee('Bs. 2,00');
        $this->actingAs($this->superadmin)->put('/tarifa-agua', ['tarifa_m3' => 0])->assertSessionHasErrors('tarifa_m3');
        $this->actingAs($this->superadmin)->put('/tarifa-agua', ['tarifa_m3' => 2.5])->assertRedirect(route('tarifa-agua.index'));

        $this->assertEquals(2.5, (float) Ajuste::valor(Ajuste::TARIFA_M3));
        $this->assertDatabaseHas('actividades', [
            'modulo' => 'tarifa_agua',
            'descripcion' => 'Cambió la tarifa del agua de Bs. 2,00 a Bs. 2,50 por m³',
        ]);
        $this->actingAs($this->superadmin)->get('/tarifa-agua')
            ->assertSee('Bs. 2,50')
            ->assertSee('Bs. 2,00 → ', false);
    }
}
