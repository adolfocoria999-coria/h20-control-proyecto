<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Ajuste;
use App\Models\BalanceMensual;
use App\Models\Lectura;
use App\Models\Multa;
use App\Models\User;
use App\Services\Arqueo;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AguaEnFinanzasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $socio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        $this->admin = User::factory()->create(['rol_id' => RolUsuario::Admin->value]);
        $this->socio = User::factory()->create(['rol_id' => RolUsuario::Socio->value]);
        Ajuste::guardar('tarifa_m3', '2.00');
    }

    // Lectura de 10 m³ = Bs. 20 con la tarifa de prueba
    private function cobrarLectura(string $cuando, int $mes = 9, string $metodo = 'efectivo'): Lectura
    {
        $this->travelTo($cuando);
        $lectura = Lectura::factory()->for($this->socio, 'usuario')
            ->create(['mes' => $mes, 'gestion' => 2026, 'lectura_anterior' => 0, 'lectura_actual' => 10]);
        $this->actingAs($this->admin)->patch("/lecturas/{$lectura->id}/pagar", ['metodo_pago' => $metodo]);

        return $lectura->fresh();
    }

    private function balance(int $mes, int $gestion = 2026): ?BalanceMensual
    {
        return BalanceMensual::firstWhere(['mes' => $mes, 'gestion' => $gestion]);
    }

    public function test_el_agua_empieza_a_sumarse_sola_desde_octubre_2026(): void
    {
        $this->assertSame('2026-10-01', BalanceMensual::aguaDesde()->toDateString());
        $this->assertFalse(BalanceMensual::aguaAutomatica(9, 2026));
        $this->assertTrue(BalanceMensual::aguaAutomatica(10, 2026));
        $this->assertTrue(BalanceMensual::aguaAutomatica(1, 2027));
    }

    public function test_cobrar_una_lectura_la_suma_al_mes_en_que_se_cobro_y_crea_el_balance(): void
    {
        // Lectura de septiembre cobrada en octubre: cuenta en octubre (cuando entró el dinero)
        $this->cobrarLectura('2026-10-20 10:00', mes: 9);

        $octubre = $this->balance(10);
        $this->assertEquals(20, $octubre->ingresos_agua);
        $this->assertEquals(0, $octubre->ingresos);
        $this->assertEquals(20, $octubre->saldo_final);
        $this->assertNull($this->balance(9));
    }

    public function test_los_meses_anteriores_al_inicio_no_cambian(): void
    {
        $septiembre = BalanceMensual::factory()->create(['mes' => 9, 'gestion' => 2026, 'ingresos' => 1200, 'egresos' => 300]);

        $this->cobrarLectura('2026-09-25 10:00', mes: 9);

        $septiembre->refresh();
        $this->assertEquals(0, $septiembre->ingresos_agua);
        $this->assertEquals(1200, $septiembre->ingresos);
        $this->assertEquals(900, $septiembre->saldo_final);
    }

    public function test_respeta_los_otros_ingresos_y_egresos_cargados_a_mano(): void
    {
        $octubre = BalanceMensual::factory()->create(['mes' => 10, 'gestion' => 2026, 'ingresos' => 50, 'egresos' => 30]);

        $this->cobrarLectura('2026-10-05 09:00', mes: 9);
        $this->cobrarLectura('2026-10-06 09:00', mes: 10);

        $octubre->refresh();
        $this->assertEquals(40, $octubre->ingresos_agua);
        $this->assertEquals(50, $octubre->ingresos);
        $this->assertEquals(90, $octubre->ingresos_totales);
        $this->assertEquals(60, $octubre->saldo_final);

        // Al editar el balance, lo automático se conserva
        $this->actingAs($this->admin)->put("/finanzas/{$octubre->id}", [
            'mes' => 10, 'gestion' => 2026, 'ingresos' => 70, 'egresos' => 30,
        ])->assertSessionHasNoErrors();
        $this->assertEquals(40, $octubre->fresh()->ingresos_agua);
        $this->assertEquals(80, $octubre->fresh()->saldo_final);
    }

    public function test_anular_o_borrar_una_lectura_cobrada_la_descuenta(): void
    {
        $primera = $this->cobrarLectura('2026-10-05 09:00', mes: 9);
        $segunda = $this->cobrarLectura('2026-10-06 09:00', mes: 10);
        $this->assertEquals(40, $this->balance(10)->ingresos_agua);

        // Volver a pendiente desde el formulario de edición
        $primera->update(['estado' => Lectura::PENDIENTE]);
        $this->assertEquals(20, $this->balance(10)->ingresos_agua);

        $segunda->delete();
        $this->assertEquals(0, $this->balance(10)->ingresos_agua);
        $this->assertEquals(0, $this->balance(10)->saldo_final);
    }

    public function test_finanzas_coincide_con_el_arqueo(): void
    {
        $this->cobrarLectura('2026-11-03 09:00', mes: 10, metodo: 'qr');
        $this->cobrarLectura('2026-11-04 09:00', mes: 11);
        $this->travelTo('2026-11-05');
        $multa = Multa::factory()->for($this->socio, 'socio')->create(['monto' => 30]);
        $this->actingAs($this->admin)->post("/multas/{$multa->id}/pagar", ['metodo_pago' => 'efectivo']);

        $noviembre = $this->balance(11);
        $arqueo = (new Arqueo(2026, 11))->resumen();

        $this->assertEquals($arqueo['agua']['total'], $noviembre->ingresos_agua);
        $this->assertEquals($arqueo['multa']['total'], $noviembre->ingresos_multas);
        $this->assertEquals($arqueo['total']['total'], $noviembre->ingresos_totales);
    }

    public function test_la_pantalla_muestra_el_desglose_y_el_enlace_al_detalle(): void
    {
        $this->cobrarLectura('2026-10-05 09:00', mes: 9);
        BalanceMensual::factory()->create(['mes' => 9, 'gestion' => 2026, 'ingresos' => 1200, 'egresos' => 0]);

        $this->actingAs($this->admin)->get('/finanzas')
            ->assertSee('Desde')
            ->assertSee('Agua: Bs. 20.00')
            ->assertSee('Otros (incl. agua): Bs. 1,200.00')
            ->assertSee(route('arqueo.index', ['gestion' => 2026, 'mes' => 10]))
            ->assertSee('Ver detalle de cobros');

        // El socio ve el desglose, pero no el enlace al arqueo (es de Hacienda)
        $this->actingAs($this->socio)->get('/finanzas')
            ->assertSee('Agua: Bs. 20.00')
            ->assertDontSee('Ver detalle de cobros');

        $csv = $this->actingAs($this->admin)->get('/finanzas/exportar')->streamedContent();
        $this->assertStringContainsString('Agua cobrada (Bs.)', $csv);
        $this->assertStringContainsString('Octubre;2026;20,00;0,00;0,00;0,00;20,00;0,00;20,00', $csv);
    }

    public function test_el_mes_de_inicio_se_puede_cambiar_en_los_ajustes(): void
    {
        Ajuste::guardar(BalanceMensual::CLAVE_AGUA_DESDE, '2026-09');

        $this->cobrarLectura('2026-09-25 10:00', mes: 9);

        $this->assertEquals(20, $this->balance(9)->ingresos_agua);
    }
}
