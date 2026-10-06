<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\BalanceMensual;
use App\Models\Lectura;
use App\Models\Multa;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodosYMultasEnFinanzasTest extends TestCase
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
    }

    // ---------- Lecturas: una por socio y periodo ----------

    public function test_no_se_puede_registrar_dos_lecturas_del_mismo_periodo(): void
    {
        Lectura::factory()->for($this->socio, 'usuario')->create(['mes' => 3, 'gestion' => 2026]);

        $this->actingAs($this->admin)->post('/lecturas', [
            'user_id' => $this->socio->id,
            'mes' => 3,
            'gestion' => 2026,
            'lectura_anterior' => 10,
            'lectura_actual' => 20,
        ])->assertSessionHasErrors(['mes' => 'Este socio ya tiene una lectura registrada para Marzo 2026. Edita esa lectura en lugar de crear otra.']);

        $this->assertSame(1, Lectura::count());

        // Otro mes, otro año u otro socio sí se permiten
        foreach ([[4, 2026, $this->socio], [3, 2025, $this->socio], [3, 2026, User::factory()->create()]] as [$mes, $gestion, $socio]) {
            $this->actingAs($this->admin)->post('/lecturas', [
                'user_id' => $socio->id, 'mes' => $mes, 'gestion' => $gestion,
                'lectura_anterior' => 0, 'lectura_actual' => 1,
            ])->assertSessionHasNoErrors();
        }
    }

    public function test_editar_una_lectura_sin_cambiar_su_periodo_no_choca_consigo_misma(): void
    {
        $lectura = Lectura::factory()->for($this->socio, 'usuario')->create(['mes' => 3, 'gestion' => 2026]);

        $this->actingAs($this->admin)->put("/lecturas/{$lectura->id}", [
            'user_id' => $this->socio->id, 'mes' => 3, 'gestion' => 2026,
            'lectura_anterior' => 0, 'lectura_actual' => 99,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(99, $lectura->fresh()->lectura_actual);
    }

    public function test_la_base_de_datos_tambien_impide_el_duplicado(): void
    {
        Lectura::factory()->for($this->socio, 'usuario')->create(['mes' => 3, 'gestion' => 2026]);

        $this->expectException(UniqueConstraintViolationException::class);
        Lectura::factory()->for($this->socio, 'usuario')->create(['mes' => 3, 'gestion' => 2026]);
    }

    public function test_el_formulario_muestra_el_error_de_duplicado(): void
    {
        Lectura::factory()->for($this->socio, 'usuario')->create(['mes' => 3, 'gestion' => 2026]);

        $this->actingAs($this->admin)->from('/lecturas/crear')->followingRedirects()->post('/lecturas', [
            'user_id' => $this->socio->id, 'mes' => 3, 'gestion' => 2026,
            'lectura_anterior' => 0, 'lectura_actual' => 1,
        ])->assertSee('ya tiene una lectura registrada para Marzo 2026');
    }

    public function test_no_se_puede_crear_dos_balances_del_mismo_mes(): void
    {
        BalanceMensual::factory()->create(['mes' => 5, 'gestion' => 2026]);

        $this->actingAs($this->admin)->post('/finanzas', [
            'mes' => 5, 'gestion' => 2026, 'ingresos' => 10, 'egresos' => 0,
        ])->assertSessionHasErrors(['mes' => 'Ya existe un balance para Mayo 2026. Edítalo en lugar de crear otro.']);
    }

    // ---------- Multas cobradas en Finanzas ----------

    public function test_cobrar_una_multa_la_suma_al_balance_del_mes_sin_tocar_los_ingresos_manuales(): void
    {
        $this->travelTo('2026-05-15');
        $balance = BalanceMensual::factory()->create(['mes' => 5, 'gestion' => 2026, 'ingresos' => 1000, 'egresos' => 200]);
        $multa = Multa::factory()->for($this->socio, 'socio')->create(['monto' => 80]);

        $this->actingAs($this->admin)->post("/multas/{$multa->id}/pagar", ['metodo_pago' => 'efectivo']);

        $balance->refresh();
        $this->assertEquals(1000, $balance->ingresos);
        $this->assertEquals(80, $balance->ingresos_multas);
        $this->assertEquals(880, $balance->saldo_final);
        $this->assertSame('2026-05-15', $multa->fresh()->fecha_pago->toDateString());
    }

    public function test_si_el_mes_no_tiene_balance_se_crea_automaticamente(): void
    {
        $this->travelTo('2026-07-01');
        $multa = Multa::factory()->for($this->socio, 'socio')->create(['monto' => 30]);

        $this->actingAs($this->admin)->post("/multas/{$multa->id}/pagar", ['metodo_pago' => 'efectivo']);

        $balance = BalanceMensual::where(['mes' => 7, 'gestion' => 2026])->sole();
        $this->assertEquals(0, $balance->ingresos);
        $this->assertEquals(30, $balance->ingresos_multas);
        $this->assertEquals(30, $balance->saldo_final);
    }

    public function test_anular_condonar_o_borrar_una_multa_cobrada_la_descuenta(): void
    {
        $this->travelTo('2026-05-15');
        $balance = BalanceMensual::factory()->create(['mes' => 5, 'gestion' => 2026, 'ingresos' => 500, 'egresos' => 0]);
        $a = Multa::factory()->for($this->socio, 'socio')->pagada()->create(['monto' => 50]);
        $b = Multa::factory()->for($this->socio, 'socio')->pagada()->create(['monto' => 30]);
        $c = Multa::factory()->for($this->socio, 'socio')->pagada()->create(['monto' => 20]);
        $this->assertEquals(100, $balance->fresh()->ingresos_multas);

        $a->update(['estado' => Multa::PENDIENTE]);
        $this->assertNull($a->fresh()->fecha_pago);
        $this->assertEquals(50, $balance->fresh()->ingresos_multas);

        $b->update(['estado' => Multa::CONDONADO]);
        $this->assertEquals(20, $balance->fresh()->ingresos_multas);

        $this->actingAs($this->admin)->delete("/multas/{$c->id}");
        $balance->refresh();
        $this->assertEquals(0, $balance->ingresos_multas);
        $this->assertEquals(500, $balance->saldo_final);
    }

    public function test_marcar_pagada_desde_el_formulario_de_edicion_tambien_la_registra(): void
    {
        $this->travelTo('2026-08-20');
        $multa = Multa::factory()->for($this->socio, 'socio')->create(['monto' => 45]);

        $this->actingAs($this->admin)->put("/multas/{$multa->id}", [
            'user_id' => $this->socio->id,
            'tarifa_multa_id' => 'otro',
            'tipo_multa_texto' => $multa->tipo_multa,
            'monto' => 45,
            'fecha_multa' => '2026-08-01',
            'estado' => Multa::PAGADO,
            'metodo_pago' => 'efectivo',
        ])->assertSessionHasNoErrors();

        $this->assertEquals(45, BalanceMensual::where(['mes' => 8, 'gestion' => 2026])->sole()->ingresos_multas);
    }

    public function test_finanzas_muestra_los_ingresos_con_multas_y_la_caja_los_incluye(): void
    {
        $this->travelTo('2026-05-15');
        BalanceMensual::factory()->create(['mes' => 5, 'gestion' => 2026, 'ingresos' => 1000, 'egresos' => 100]);
        Multa::factory()->for($this->socio, 'socio')->pagada()->create(['monto' => 80]);

        $this->actingAs($this->socio)->get('/finanzas')
            ->assertViewHas('cajaActual', 980.0)
            ->assertViewHas('totalIngresos', 1080.0)
            ->assertSee('incl. Bs. 80.00 de multas');

        $csv = $this->actingAs($this->admin)->get('/finanzas/exportar')->streamedContent();
        $this->assertStringContainsString('Multas cobradas (Bs.)', $csv);
        $this->assertStringContainsString('1000,00;80,00;100,00;980,00', $csv);
    }

    public function test_editar_el_balance_conserva_lo_cobrado_por_multas(): void
    {
        $this->travelTo('2026-05-15');
        $balance = BalanceMensual::factory()->create(['mes' => 5, 'gestion' => 2026, 'ingresos' => 1000, 'egresos' => 0]);
        Multa::factory()->for($this->socio, 'socio')->pagada()->create(['monto' => 80]);

        $this->actingAs($this->admin)->put("/finanzas/{$balance->id}", [
            'mes' => 5, 'gestion' => 2026, 'ingresos' => 1500, 'egresos' => 300,
        ])->assertSessionHasNoErrors();

        $balance->refresh();
        $this->assertEquals(80, $balance->ingresos_multas);
        $this->assertEquals(1280, $balance->saldo_final);
    }
}
