<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Ajuste;
use App\Models\Lectura;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvisoLecturaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $juan;

    private User $sinCelular;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        Ajuste::guardar(Ajuste::TARIFA_M3, '2.00');
        $this->admin = User::factory()->create(['rol_id' => RolUsuario::Admin->value]);
        $this->juan = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Juan Pérez', 'telefono' => '71234567']);
        $this->sinCelular = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Rosa Sin Cel', 'telefono' => null]);
    }

    private function lectura(User $socio, int $mes, float $anterior, float $actual): Lectura
    {
        return Lectura::factory()->for($socio, 'usuario')
            ->create(['mes' => $mes, 'gestion' => 2026, 'lectura_anterior' => $anterior, 'lectura_actual' => $actual]);
    }

    private function mensajeDe(string $enlace): string
    {
        parse_str(parse_url($enlace, PHP_URL_QUERY), $consulta);

        return $consulta['text'];
    }

    public function test_avisar_abre_whatsapp_con_el_mensaje_y_lo_marca(): void
    {
        $septiembre = $this->lectura($this->juan, 9, 1500, 1512);
        $octubre = $this->lectura($this->juan, 10, 1512, 1527);

        $respuesta = $this->actingAs($this->admin)->get("/lecturas/{$octubre->id}/avisar");
        $enlace = $respuesta->headers->get('Location');
        $this->assertStringStartsWith('https://wa.me/59171234567?text=', $enlace);

        $mensaje = $this->mensajeDe($enlace);
        $this->assertStringContainsString('Sr(a). Juan Pérez', $mensaje);
        $this->assertStringContainsString('Su lectura de agua de Octubre 2026 ya está registrada', $mensaje);
        $this->assertStringContainsString('Medidor: 1512 m³ → 1527 m³ (consumo de 15 m³)', $mensaje);
        $this->assertStringContainsString('Monto a pagar: Bs. 30,00', $mensaje);
        // Septiembre sigue sin pagar: se le recuerda y se suma
        $this->assertStringContainsString('Además tiene pendiente: Septiembre 2026 (Bs. 24,00)', $mensaje);
        $this->assertStringContainsString('Total adeudado: Bs. 54,00', $mensaje);
        $this->assertStringContainsString(route('socio.consumo'), $mensaje);

        $this->assertNotNull($octubre->fresh()->avisado_at);
        $this->assertNull($septiembre->fresh()->avisado_at);
        $this->assertDatabaseHas('actividades', [
            'accion' => 'notificacion',
            'descripcion' => 'Envió por WhatsApp la lectura de Octubre / 2026 a Juan Pérez (59171234567) por Bs. 30,00',
        ]);

        // La segunda vez queda como reenvío
        $this->actingAs($this->admin)->get("/lecturas/{$octubre->id}/avisar");
        $this->assertDatabaseHas('actividades', ['descripcion' => 'Reenvió por WhatsApp la lectura de Octubre / 2026 a Juan Pérez (59171234567) por Bs. 30,00']);
    }

    public function test_sin_celular_o_ya_pagada_no_se_avisa(): void
    {
        $deRosa = $this->lectura($this->sinCelular, 10, 0, 10);
        $deJuan = $this->lectura($this->juan, 10, 0, 10);
        $this->actingAs($this->admin)->patch("/lecturas/{$deJuan->id}/pagar", ['metodo_pago' => 'qr']);

        $this->actingAs($this->admin)->from('/lecturas/avisos')->get("/lecturas/{$deRosa->id}/avisar")
            ->assertRedirect('/lecturas/avisos')
            ->assertSessionHas('error', 'Rosa Sin Cel no tiene un número de celular válido registrado: avísale en persona.');
        $this->actingAs($this->admin)->from('/lecturas/avisos')->get("/lecturas/{$deJuan->id}/avisar")
            ->assertSessionHas('error', 'La lectura de Juan Pérez ya está pagada: no hace falta avisar.');

        $this->assertNull($deRosa->fresh()->avisado_at);
    }

    public function test_la_lista_muestra_a_quien_falta_avisar(): void
    {
        $avisada = $this->lectura($this->juan, 10, 0, 10);
        $this->lectura($this->sinCelular, 10, 0, 10);
        $otro = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Pedro Choque', 'telefono' => '61112233']);
        $this->lectura($otro, 10, 0, 20);
        $this->actingAs($this->admin)->get("/lecturas/{$avisada->id}/avisar");

        $this->actingAs($this->admin)->get('/lecturas/avisos?mes=10&gestion=2026')
            ->assertOk()
            ->assertSee('Avisar lecturas – Octubre 2026')
            ->assertViewHas('resumen', ['por_avisar' => 1, 'avisados' => 1, 'sin_celular' => 1, 'pagadas' => 0])
            ->assertSee('✅ Avisado')
            ->assertSee('📵 Avisar en persona')
            ->assertSee(route('lecturas.avisar', Lectura::where('user_id', $otro->id)->sole()));

        // Accesos desde Lecturas y desde la planilla
        $this->actingAs($this->admin)->get('/lecturas')->assertSee(route('lecturas.avisos'));
        $this->actingAs($this->admin)->get('/lecturas/planilla?mes=10&gestion=2026')
            ->assertSee(route('lecturas.avisos', ['mes' => 10, 'gestion' => 2026]));
    }

    public function test_solo_hacienda_y_el_presidente_avisan(): void
    {
        $lectura = $this->lectura($this->juan, 10, 0, 10);
        $plomero = User::factory()->create(['rol_id' => RolUsuario::Plomero->value]);

        foreach ([$this->juan, $plomero] as $usuario) {
            $this->actingAs($usuario)->get('/lecturas/avisos')->assertForbidden();
            $this->actingAs($usuario)->get("/lecturas/{$lectura->id}/avisar")->assertForbidden();
        }
        $this->actingAs($plomero)->get('/lecturas/planilla')->assertDontSee(route('lecturas.avisos'));
    }
}
