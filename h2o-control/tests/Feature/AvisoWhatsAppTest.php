<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Actividad;
use App\Models\Lectura;
use App\Models\Multa;
use App\Models\User;
use App\Support\WhatsApp;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvisoWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        $this->admin = User::factory()->create(['rol_id' => RolUsuario::Admin->value]);
        config(['otb.nombre' => 'OTB Chulla Jayata', 'otb.telefono_finanzas' => '63883052']);
    }

    private function socio(array $datos = []): User
    {
        return User::factory()->create($datos + ['rol_id' => RolUsuario::Socio->value, 'name' => 'Valerio Coria', 'telefono' => '68543387']);
    }

    // 10 m³ * Bs. 2 = Bs. 20 por mes
    private function deberMeses(User $socio, array $meses): void
    {
        foreach ($meses as $mes) {
            Lectura::factory()->for($socio, 'usuario')->create(['mes' => $mes, 'gestion' => 2026, 'lectura_anterior' => 0, 'lectura_actual' => 10]);
        }
    }

    /** Sigue la redirección y devuelve el mensaje decodificado. */
    private function mensajeEnviado(User $socio): string
    {
        $url = $this->actingAs($this->admin)->get("/reportes/socios/{$socio->id}/whatsapp?gestion=2026")
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringStartsWith('https://wa.me/59168543387?text=', $url);

        return rawurldecode(substr($url, strpos($url, '?text=') + 6));
    }

    public function test_normaliza_celulares_bolivianos(): void
    {
        $this->assertSame('59168543387', WhatsApp::numero('68543387'));
        $this->assertSame('59168543387', WhatsApp::numero('6854-3387'));
        $this->assertSame('59168543387', WhatsApp::numero('+591 68543387'));
        $this->assertNull(WhatsApp::numero('12345'));
        $this->assertNull(WhatsApp::numero(null));
    }

    public function test_menos_de_3_meses_envia_un_recordatorio(): void
    {
        $socio = $this->socio();
        $this->deberMeses($socio, [8, 9]);

        $mensaje = $this->mensajeEnviado($socio);

        $this->assertStringContainsString('Sr(a). Valerio Coria, le saluda la OTB Chulla Jayata.', $mensaje);
        $this->assertStringContainsString('Le recordamos que debe cancelar 2 meses de consumo de agua potable (Agosto 2026 y Septiembre 2026) por Bs. 40,00.', $mensaje);
        $this->assertStringContainsString('Consultas: 63883052.', $mensaje);
        $this->assertStringNotContainsString('CORTE', $mensaje);
    }

    public function test_3_o_mas_meses_envia_aviso_de_corte_con_multas(): void
    {
        $socio = $this->socio();
        $this->deberMeses($socio, [7, 8, 9]);
        Multa::factory()->for($socio, 'socio')->create(['monto' => 30, 'fecha_multa' => '2026-09-07']);

        $mensaje = $this->mensajeEnviado($socio);

        $this->assertStringContainsString('AVISO DE CORTE: tiene pendiente el pago de 3 meses de consumo de agua potable (Julio 2026, Agosto 2026 y Septiembre 2026) por Bs. 60,00 y multas por Bs. 30,00.', $mensaje);
        $this->assertStringContainsString('Total adeudado: Bs. 90,00.', $mensaje);
        $this->assertStringContainsString('corresponde el corte del servicio de agua potable', $mensaje);
    }

    public function test_un_solo_mes_nombra_el_mes(): void
    {
        $socio = $this->socio();
        $this->deberMeses($socio, [9]);

        $this->assertStringContainsString('debe cancelar su consumo de agua potable de Septiembre 2026 por Bs. 20,00.', $this->mensajeEnviado($socio));
    }

    public function test_el_aviso_queda_en_el_historial(): void
    {
        $socio = $this->socio();
        $this->deberMeses($socio, [7, 8, 9]);

        $this->mensajeEnviado($socio);

        $actividad = Actividad::where('accion', 'notificacion')->sole();
        $this->assertSame('Envió por WhatsApp un aviso de corte a Valerio Coria (59168543387) por Bs. 60,00', $actividad->descripcion);
        $this->assertSame(Actividad::SECCION_ADMIN, $actividad->seccion);
    }

    public function test_sin_celular_o_sin_deuda_no_abre_whatsapp(): void
    {
        $sinCelular = $this->socio(['telefono' => null, 'name' => 'Sin Celular']);
        $this->deberMeses($sinCelular, [9]);
        $alDia = $this->socio(['name' => 'Al Dia']);

        $this->actingAs($this->admin)->from('/reportes')->get("/reportes/socios/{$sinCelular->id}/whatsapp?gestion=2026")
            ->assertRedirect('/reportes')->assertSessionHas('error', 'Sin Celular no tiene un número de celular válido registrado.');
        $this->actingAs($this->admin)->from('/reportes')->get("/reportes/socios/{$alDia->id}/whatsapp?gestion=2026")
            ->assertRedirect('/reportes')->assertSessionHas('error', 'Al Dia no tiene deudas pendientes en el periodo seleccionado.');

        $this->assertSame(0, Actividad::where('accion', 'notificacion')->count());
    }

    public function test_el_reporte_muestra_el_boton_segun_la_situacion(): void
    {
        $corte = $this->socio(['name' => 'Debe Tres']);
        $this->deberMeses($corte, [7, 8, 9]);
        $recordatorio = $this->socio(['name' => 'Debe Uno', 'telefono' => '70011223']);
        $this->deberMeses($recordatorio, [9]);
        $sinCelular = $this->socio(['name' => 'Sin Cel', 'telefono' => '']);
        $this->deberMeses($sinCelular, [9]);

        $this->actingAs($this->admin)->get('/reportes?gestion=2026')->assertOk()
            ->assertSee('🚨 Aviso de corte')
            ->assertSee('⚠️ Notificar')
            ->assertSee('📵 Sin celular')
            ->assertSee(route('reportes.notificar', ['socio' => $corte->id, 'gestion' => 2026]), false)
            ->assertSee('target="_blank"', false);
    }

    public function test_el_socio_no_puede_usar_el_aviso(): void
    {
        $socio = $this->socio();

        $this->actingAs($socio)->get("/reportes/socios/{$socio->id}/whatsapp")->assertForbidden();
    }
}
