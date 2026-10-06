<?php

namespace Tests\Feature;

use App\Enums\MetodoPago;
use App\Enums\RolUsuario;
use App\Models\Ajuste;
use App\Models\Lectura;
use App\Models\Multa;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagosYArqueoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $socio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        $this->admin = User::factory()->create(['rol_id' => RolUsuario::Admin->value, 'name' => 'Maria Hacienda']);
        $this->socio = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Ana Quispe']);
    }

    private function lectura(array $datos = []): Lectura
    {
        return Lectura::factory()->for($this->socio, 'usuario')->create($datos + ['lectura_anterior' => 0, 'lectura_actual' => 10]);
    }

    // ---------- Registro del pago ----------

    public function test_pagar_una_lectura_exige_elegir_qr_o_efectivo(): void
    {
        $lectura = $this->lectura();

        $this->actingAs($this->admin)->patch("/lecturas/{$lectura->id}/pagar")
            ->assertSessionHasErrors(['metodo_pago' => 'Elige si el pago fue por QR o en efectivo.']);
        $this->actingAs($this->admin)->patch("/lecturas/{$lectura->id}/pagar", ['metodo_pago' => 'tarjeta'])
            ->assertSessionHasErrors('metodo_pago');

        $this->assertSame(Lectura::PENDIENTE, $lectura->fresh()->estado);
    }

    public function test_el_pago_guarda_metodo_fecha_monto_y_quien_cobro(): void
    {
        $this->travelTo('2026-10-20 15:30:00');
        $lectura = $this->lectura();

        $this->actingAs($this->admin)->patch("/lecturas/{$lectura->id}/pagar", ['metodo_pago' => 'qr'])
            ->assertSessionHas('success');

        $lectura->refresh();
        $this->assertSame(Lectura::PAGADO, $lectura->estado);
        $this->assertSame(MetodoPago::Qr, $lectura->metodo_pago);
        $this->assertSame('2026-10-20 15:30:00', $lectura->fecha_pago->format('Y-m-d H:i:s'));
        $this->assertEquals(20, $lectura->monto_pagado);
        $this->assertTrue($lectura->cobrador->is($this->admin));
    }

    public function test_no_se_puede_cobrar_dos_veces(): void
    {
        $lectura = $this->lectura();
        $this->actingAs($this->admin)->patch("/lecturas/{$lectura->id}/pagar", ['metodo_pago' => 'qr']);

        $this->actingAs($this->admin)->patch("/lecturas/{$lectura->id}/pagar", ['metodo_pago' => 'efectivo'])
            ->assertSessionHas('error', 'Esta lectura ya está pagada.');

        $this->assertSame(MetodoPago::Qr, $lectura->fresh()->metodo_pago);
    }

    public function test_el_monto_cobrado_no_cambia_si_luego_cambia_la_tarifa(): void
    {
        $lectura = $this->lectura();
        $this->actingAs($this->admin)->patch("/lecturas/{$lectura->id}/pagar", ['metodo_pago' => 'efectivo']);

        Ajuste::where('clave', Ajuste::TARIFA_M3)->update(['valor' => '5.00']);

        $this->assertEquals(20, $lectura->fresh()->monto_pagado);
    }

    public function test_multas_registran_el_metodo_y_al_anular_se_borra(): void
    {
        $multa = Multa::factory()->for($this->socio, 'socio')->create(['monto' => 30]);

        $this->actingAs($this->admin)->post("/multas/{$multa->id}/pagar")->assertSessionHasErrors('metodo_pago');
        $this->actingAs($this->admin)->post("/multas/{$multa->id}/pagar", ['metodo_pago' => 'efectivo']);

        $multa->refresh();
        $this->assertSame(MetodoPago::Efectivo, $multa->metodo_pago);
        $this->assertTrue($multa->cobrador->is($this->admin));

        $multa->update(['estado' => Multa::PENDIENTE]);
        $multa->refresh();
        $this->assertNull($multa->metodo_pago);
        $this->assertNull($multa->cobrado_por);
    }

    public function test_marcar_pagada_al_editar_una_multa_exige_el_metodo(): void
    {
        $multa = Multa::factory()->for($this->socio, 'socio')->create();
        $datos = [
            'user_id' => $this->socio->id, 'tarifa_multa_id' => 'otro', 'tipo_multa_texto' => $multa->tipo_multa,
            'monto' => 30, 'fecha_multa' => '2026-10-01', 'estado' => Multa::PAGADO,
        ];

        $this->actingAs($this->admin)->put("/multas/{$multa->id}", $datos)
            ->assertSessionHasErrors(['metodo_pago' => 'El campo método de pago es obligatorio cuando estado es pagado.']);

        $this->actingAs($this->admin)->put("/multas/{$multa->id}", $datos + ['metodo_pago' => 'qr'])->assertSessionHasNoErrors();
        $this->assertSame(MetodoPago::Qr, $multa->fresh()->metodo_pago);
    }

    public function test_las_listas_tienen_el_modal_de_pago(): void
    {
        $this->lectura();
        Multa::factory()->for($this->socio, 'socio')->create();

        foreach (['/lecturas', '/multas'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk()
                ->assertSee('¿Cómo pagó el socio?')
                ->assertSee('name="metodo_pago" value="qr"', false)
                ->assertSee('name="metodo_pago" value="efectivo"', false)
                ->assertSee('Cancelar')
                ->assertSee('data-accion=', false);
        }
    }

    // ---------- Arqueo ----------

    public function test_el_arqueo_suma_por_metodo_segun_la_fecha_de_cobro(): void
    {
        $this->actingAs($this->admin);

        // Cobradas en octubre (aunque una sea la lectura de septiembre)
        $this->travelTo('2026-10-05 10:00:00');
        $this->lectura(['mes' => 9, 'gestion' => 2026])->update(['estado' => Lectura::PAGADO, 'metodo_pago' => 'qr']);           // Bs. 20
        $this->lectura(['mes' => 10, 'gestion' => 2026])->update(['estado' => Lectura::PAGADO, 'metodo_pago' => 'efectivo']);    // Bs. 20
        Multa::factory()->for($this->socio, 'socio')->create(['monto' => 30])->update(['estado' => Multa::PAGADO, 'metodo_pago' => 'efectivo']);

        // Cobrada en noviembre: no entra en octubre
        $this->travelTo('2026-11-02 10:00:00');
        $this->lectura(['mes' => 11, 'gestion' => 2026])->update(['estado' => Lectura::PAGADO, 'metodo_pago' => 'qr']);

        $this->get('/finanzas/arqueo?gestion=2026&mes=10')->assertOk()
            ->assertViewHas('resumen', function ($r) {
                return $r['agua']['qr'] == 20 && $r['agua']['efectivo'] == 20
                    && $r['multa']['efectivo'] == 30 && $r['multa']['qr'] == 0
                    && $r['total']['qr'] == 20 && $r['total']['efectivo'] == 50 && $r['total']['total'] == 70
                    && $r['total']['cantidad'] === 3;
            })
            ->assertSee('Arqueo de Caja – Octubre 2026')
            ->assertSee('Maria Hacienda');
    }

    public function test_cobros_antiguos_sin_metodo_se_muestran_aparte(): void
    {
        $this->travelTo('2026-10-05 10:00:00');
        $lectura = $this->lectura();
        // Pagada antes de existir el método de pago
        Lectura::withoutEvents(fn () => $lectura->forceFill(['estado' => Lectura::PAGADO, 'fecha_pago' => now(), 'monto_pagado' => 20])->save());

        $this->actingAs($this->admin)->get('/finanzas/arqueo?gestion=2026&mes=10')
            ->assertSee('Sin especificar')
            ->assertSee('antes de que el sistema pidiera el método de pago');
    }

    public function test_descargar_el_arqueo_en_excel(): void
    {
        $this->actingAs($this->admin);
        $this->travelTo('2026-10-05 10:00:00');
        $this->lectura()->update(['estado' => Lectura::PAGADO, 'metodo_pago' => 'qr']);

        $respuesta = $this->get('/finanzas/arqueo/exportar?gestion=2026&mes=10')->assertOk();
        $csv = $respuesta->streamedContent();

        $this->assertStringContainsString('arqueo_2026_10.csv', $respuesta->headers->get('content-disposition'));
        $this->assertStringContainsString('"Fecha de cobro";Tipo;Concepto;Socio;Método;"Cobrado por";"Monto (Bs.)"', $csv);
        $this->assertStringContainsString('"Ana Quispe";QR;"Maria Hacienda";20,00', $csv);
        $this->assertStringContainsString('"RESUMEN DEL ARQUEO – Octubre 2026"', $csv);
        $this->assertStringContainsString('"TOTAL RECAUDADO";;"QR: 20,00";"Efectivo: 0,00";20,00', $csv);
    }

    public function test_solo_la_administracion_ve_el_arqueo(): void
    {
        $this->actingAs($this->socio)->get('/finanzas/arqueo')->assertForbidden();
        $this->actingAs($this->socio)->get('/finanzas/arqueo/exportar')->assertForbidden();
        $this->actingAs($this->socio)->get('/finanzas')->assertDontSee('Arqueo mensual');

        $this->actingAs($this->admin)->get('/finanzas')->assertSee('Arqueo mensual');
        $this->actingAs($this->admin)->get('/finanzas/arqueo')->assertOk();
    }
}
