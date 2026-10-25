<?php

namespace Tests\Feature;

use App\Enums\MetodoPago;
use App\Enums\RolUsuario;
use App\Models\Aporte;
use App\Models\BalanceMensual;
use App\Models\Comunicado;
use App\Models\Multa;
use App\Models\User;
use App\Services\NotificacionPush;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AportesTest extends TestCase
{
    use RefreshDatabase;

    private User $hacienda;

    private User $juan;

    private User $rosa;

    private User $pedro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        $this->hacienda = User::factory()->create(['rol_id' => RolUsuario::Admin->value, 'name' => 'María Hacienda']);
        $this->juan = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Juan Pérez']);
        $this->rosa = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Rosa Condori']);
        $this->pedro = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Pedro Choque']);
        User::factory()->create(['rol_id' => RolUsuario::Plomero->value]);

        // Sin red: las notificaciones al celular no salen en las pruebas
        $this->app->instance(NotificacionPush::class, new class extends NotificacionPush
        {
            protected function despachar(array $envios): array
            {
                return [];
            }
        });
        $this->withoutDefer();
    }

    private function registrarAporte(array $datos = [])
    {
        return $this->actingAs($this->hacienda)->post('/aportes', $datos + [
            'titulo' => 'Aniversario de la OTB',
            'descripcion' => 'Refrigerio y banda para el aniversario.',
            'monto' => '20',
            'fecha_limite' => now()->addDays(10)->toDateString(),
            'notificar' => '1',
        ]);
    }

    public function test_registrar_un_aporte_anota_la_cuota_a_cada_socio_y_avisa(): void
    {
        $this->registrarAporte()->assertRedirect()->assertSessionHas('success');

        $aporte = Aporte::sole();
        $this->assertSame('Aniversario de la OTB', $aporte->titulo);
        $this->assertSame($this->hacienda->id, $aporte->user_id);

        // Una cuota pendiente por socio (no al plomero ni a la directiva)
        $cuotas = Multa::soloAportes()->get();
        $this->assertCount(3, $cuotas);
        $this->assertEqualsCanonicalizing([$this->juan->id, $this->rosa->id, $this->pedro->id], $cuotas->pluck('user_id')->all());
        $this->assertTrue($cuotas->every(fn ($c) => $c->estado === Multa::PENDIENTE && (float) $c->monto === 20.0 && $c->aporte_id === $aporte->id));

        // La notificación con la descripción, el monto, la fecha límite y cómo pagar
        $comunicado = Comunicado::sole();
        $this->assertSame($comunicado->id, $aporte->comunicado_id);
        $this->assertSame('Aporte: Aniversario de la OTB', $comunicado->titulo);
        $this->assertStringContainsString('Refrigerio y banda para el aniversario.', $comunicado->mensaje);
        $this->assertStringContainsString('Monto por socio: Bs. 20,00', $comunicado->mensaje);
        $this->assertStringContainsString('Fecha límite: '.now()->addDays(10)->format('d/m/Y'), $comunicado->mensaje);
        $this->assertStringContainsString('QR', $comunicado->mensaje);

        // El socio ve su cuota en Multas y Aportes y la notificación
        $this->actingAs($this->juan)->get('/multas')->assertOk()
            ->assertSee('Multas y Aportes')->assertSee('🎉 Aporte')->assertSee('Aniversario de la OTB');
        $this->actingAs($this->juan)->get('/notificaciones')->assertSee('Aporte: Aniversario de la OTB');
    }

    public function test_sin_notificar_no_envia_nada(): void
    {
        $this->registrarAporte(['notificar' => null]);

        $this->assertSame(1, Aporte::count());
        $this->assertSame(0, Comunicado::count());
    }

    public function test_validaciones_y_permisos(): void
    {
        $this->registrarAporte(['titulo' => '', 'monto' => '0'])->assertSessionHasErrors(['titulo', 'monto']);
        $this->registrarAporte(['fecha_limite' => now()->subDay()->toDateString()])->assertSessionHasErrors('fecha_limite');
        $this->assertSame(0, Aporte::count());

        $this->actingAs($this->juan)->post('/aportes', ['titulo' => 'X', 'monto' => 5])->assertForbidden();
        $this->actingAs($this->juan)->get('/aportes/crear')->assertForbidden();
    }

    public function test_cobrar_una_cuota_va_a_finanzas_arqueo_y_recibo_como_aporte(): void
    {
        $this->registrarAporte();
        $cuota = Multa::soloAportes()->where('user_id', $this->juan->id)->sole();

        $this->actingAs($this->hacienda)->post("/multas/{$cuota->id}/pagar", ['metodo_pago' => MetodoPago::Qr->value])
            ->assertSessionHas('success', 'Pago del aporte registrado (QR). ✅');

        // Una multa normal pagada el mismo mes, para ver que van separadas
        $multa = Multa::factory()->create(['user_id' => $this->rosa->id, 'monto' => 30, 'estado' => Multa::PENDIENTE]);
        $multa->update(['estado' => Multa::PAGADO, 'metodo_pago' => MetodoPago::Efectivo]);

        $balance = BalanceMensual::firstWhere(['mes' => now()->month, 'gestion' => now()->year]);
        $this->assertEquals(20, $balance->ingresos_aportes);
        $this->assertEquals(30, $balance->ingresos_multas);
        $this->assertEquals(50, $balance->ingresos_totales);

        $this->actingAs($this->hacienda)->get('/finanzas')->assertSee('Aportes: Bs. 20.00')->assertSee('Multas: Bs. 30.00');
        $this->actingAs($this->hacienda)->get('/finanzas/arqueo?gestion='.now()->year.'&mes='.now()->month)
            ->assertSee('🎉 Aportes')->assertSee('Aporte – Aniversario de la OTB');

        // Recibo y Mis pagos lo muestran como aporte
        $this->actingAs($this->juan)->get(route('recibos.multa', $cuota))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->juan)->get('/mis-pagos')->assertSee('Aporte – Aniversario de la OTB');
    }

    public function test_el_avance_muestra_quien_pago_y_quien_falta(): void
    {
        $this->registrarAporte();
        $aporte = Aporte::sole();
        $cuotas = $aporte->cuotas()->get()->keyBy('user_id');

        $this->actingAs($this->hacienda)->post("/multas/{$cuotas[$this->juan->id]->id}/pagar", ['metodo_pago' => 'efectivo']);
        $this->actingAs($this->hacienda)->patch("/multas/{$cuotas[$this->pedro->id]->id}/condonar")->assertSessionHas('success');

        $avance = Aporte::conAvance()->find($aporte->id)->avance;
        $this->assertSame(['socios' => 3, 'pagaron' => 1, 'condonados' => 1, 'recaudado' => 20.0, 'esperado' => 40.0, 'porcentaje' => 50], $avance);

        $this->actingAs($this->hacienda)->get('/multas')->assertSee('1 de 2 socios pagaron')->assertSee('Quién falta');

        // "Quién falta": solo Rosa
        $this->actingAs($this->hacienda)->get("/multas?aporte={$aporte->id}&estado=pendiente")
            ->assertSee('Rosa Condori')->assertDontSee('Juan Pérez')->assertDontSee('Pedro Choque');
    }

    public function test_un_aporte_solo_se_elimina_si_nadie_pago(): void
    {
        $this->registrarAporte();
        $aporte = Aporte::sole();

        $cuota = $aporte->cuotas()->first();
        $this->actingAs($this->hacienda)->post("/multas/{$cuota->id}/pagar", ['metodo_pago' => 'qr']);
        $this->actingAs($this->hacienda)->delete("/aportes/{$aporte->id}")->assertSessionHas('error');
        $this->assertSame(3, $aporte->cuotas()->count());

        // Otro aporte sin pagos: se borra con sus cuotas y su notificación
        $this->registrarAporte(['titulo' => 'Compra de tubería']);
        $otro = Aporte::where('titulo', 'Compra de tubería')->sole();
        $this->actingAs($this->hacienda)->delete("/aportes/{$otro->id}")->assertSessionHas('success');
        $this->assertNull(Aporte::find($otro->id));
        $this->assertSame(0, Multa::where('aporte_id', $otro->id)->count());
        $this->assertSame(0, Comunicado::where('titulo', 'Aporte: Compra de tubería')->count());
    }

    public function test_condonar_solo_cobros_pendientes(): void
    {
        $multa = Multa::factory()->create(['user_id' => $this->rosa->id, 'estado' => Multa::PENDIENTE]);

        $this->actingAs($this->juan)->patch("/multas/{$multa->id}/condonar")->assertForbidden();
        $this->actingAs($this->hacienda)->patch("/multas/{$multa->id}/condonar")->assertSessionHas('success');
        $this->assertSame(Multa::CONDONADO, $multa->fresh()->estado);
        $this->actingAs($this->hacienda)->patch("/multas/{$multa->id}/condonar")->assertSessionHas('error');
    }

    public function test_filtro_por_tipo(): void
    {
        $this->registrarAporte();
        Multa::factory()->create(['user_id' => $this->rosa->id, 'tipo_multa' => 'Inasistencia a la faena']);

        $this->actingAs($this->hacienda)->get('/multas?tipo=multas')->assertSee('Inasistencia a la faena')->assertDontSee('🎉 Aporte</span>', false);
        $this->actingAs($this->hacienda)->get('/multas?tipo=aportes')->assertSee('Aniversario de la OTB')->assertDontSee('Inasistencia a la faena');
    }
}
