<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Actividad;
use App\Models\BalanceMensual;
use App\Models\Lectura;
use App\Models\Multa;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistorialTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private User $admin;

    private User $socio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);

        $this->superadmin = User::factory()->create(['rol_id' => RolUsuario::SuperAdmin->value, 'name' => 'Adolfo Coria']);
        $this->admin = User::factory()->create(['rol_id' => RolUsuario::Admin->value, 'name' => 'Maria Flores']);
        $this->socio = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Ana Quispe']);
    }

    // ---------- Qué se registra ----------

    public function test_las_acciones_del_admin_van_al_apartado_de_administracion(): void
    {
        $this->actingAs($this->admin)->post('/lecturas', [
            'user_id' => $this->socio->id, 'mes' => 3, 'gestion' => 2026,
            'lectura_anterior' => 100, 'lectura_actual' => 110,
        ]);

        $actividad = Actividad::where('modulo', 'lecturas')->sole();
        $this->assertSame(Actividad::SECCION_ADMIN, $actividad->seccion);
        $this->assertSame('creado', $actividad->accion);
        $this->assertSame('Maria Flores', $actividad->usuario_nombre);
        $this->assertSame('admin', $actividad->rol);
        $this->assertSame('Registró la lectura de Marzo / 2026 de Ana Quispe', $actividad->descripcion);
        $this->assertSame(['antes' => null, 'despues' => 110], $actividad->cambios['lectura_actual']);
        $this->assertTrue($actividad->sujeto->is(Lectura::sole()));
    }

    public function test_editar_guarda_solo_lo_que_cambio_con_valor_anterior_y_nuevo(): void
    {
        $lectura = Lectura::factory()->for($this->socio, 'usuario')->create(['mes' => 3, 'gestion' => 2026, 'lectura_anterior' => 100, 'lectura_actual' => 110]);

        $this->actingAs($this->admin)->put("/lecturas/{$lectura->id}", [
            'user_id' => $this->socio->id, 'mes' => 3, 'gestion' => 2026,
            'lectura_anterior' => 100, 'lectura_actual' => 125,
        ]);

        $cambios = Actividad::where('accion', 'actualizado')->sole()->cambios;
        $this->assertSame(['lectura_actual', 'consumo'], array_keys($cambios));
        $this->assertEquals(110, $cambios['lectura_actual']['antes']);
        $this->assertEquals(125, $cambios['lectura_actual']['despues']);
    }

    public function test_pagos_y_condonaciones_tienen_su_propia_accion(): void
    {
        $lectura = Lectura::factory()->for($this->socio, 'usuario')->create(['mes' => 4, 'gestion' => 2026, 'lectura_anterior' => 0, 'lectura_actual' => 10]);
        $multa = Multa::factory()->for($this->socio, 'socio')->create(['monto' => 30, 'tipo_multa' => 'Inasistencia']);
        $otra = Multa::factory()->for($this->socio, 'socio')->create(['monto' => 50, 'tipo_multa' => 'Faena']);

        $this->actingAs($this->admin)->patch("/lecturas/{$lectura->id}/pagar", ['metodo_pago' => 'efectivo']);
        $this->actingAs($this->admin)->post("/multas/{$multa->id}/pagar", ['metodo_pago' => 'qr']);
        $otra->update(['estado' => Multa::CONDONADO]);

        $this->assertSame('Registró el pago en efectivo de la lectura de Abril / 2026 de Ana Quispe (Bs. 20,00)', Actividad::where('modulo', 'lecturas')->where('accion', 'pago')->sole()->descripcion);
        $this->assertSame('Registró el pago por QR de la multa «Inasistencia» de Bs. 30,00 de Ana Quispe', Actividad::where('modulo', 'multas')->where('accion', 'pago')->sole()->descripcion);
        $this->assertSame(1, Actividad::where('accion', 'condonacion')->count());

        // El balance creado automáticamente se registra una vez; el recálculo de multas no genera ruido
        $this->assertSame(1, Actividad::where('modulo', 'finanzas')->count());
        $this->assertStringContainsString('automáticamente', Actividad::where('modulo', 'finanzas')->sole()->descripcion);
    }

    public function test_dar_de_baja_un_socio_y_las_contrasenas_nunca_se_guardan(): void
    {
        $this->actingAs($this->superadmin)->put("/usuarios/{$this->socio->id}", [
            'name' => 'Ana Quispe', 'email' => $this->socio->email, 'password' => 'nueva-clave-123',
            'rol_id' => RolUsuario::Socio->value,
        ]);
        $this->actingAs($this->superadmin)->delete("/usuarios/{$this->socio->id}");

        $edicion = Actividad::where('modulo', 'usuarios')->where('accion', 'actualizado')->sole();
        $this->assertSame('Actualizó los datos de Ana Quispe', $edicion->descripcion);
        $this->assertSame('••••••', $edicion->cambios['password']['despues']);
        $this->assertStringNotContainsString('nueva-clave', json_encode(Actividad::pluck('cambios')));

        $this->assertSame('Dio de baja a Ana Quispe', Actividad::where('accion', 'baja')->sole()->descripcion);
    }

    public function test_los_inicios_y_cierres_de_sesion_no_se_registran(): void
    {
        $this->post('/login', ['email' => $this->socio->email, 'password' => 'password']);
        $this->post('/logout');
        $this->post('/login', ['email' => 'nadie@otb.com', 'password' => 'x']);

        $this->assertSame(0, Actividad::count());
    }

    public function test_el_bloqueo_por_demasiados_intentos_si_se_registra(): void
    {
        // Breeze bloquea al sexto intento fallido seguido
        foreach (range(1, 6) as $intento) {
            $this->post('/login', ['email' => $this->socio->email, 'password' => 'incorrecta']);
        }

        $bloqueo = Actividad::sole();
        $this->assertSame('bloqueo', $bloqueo->accion);
        $this->assertSame(Actividad::SECCION_USUARIO, $bloqueo->seccion);
        $this->assertSame('Ana Quispe', $bloqueo->usuario_nombre);
    }

    public function test_cambiar_la_contrasena_desde_el_perfil_se_registra(): void
    {
        $this->actingAs($this->socio)->put('/password', [
            'current_password' => 'password',
            'password' => 'nueva-clave-segura-1',
            'password_confirmation' => 'nueva-clave-segura-1',
        ])->assertSessionHasNoErrors();

        $actividad = Actividad::sole();
        $this->assertSame('Cambió su contraseña', $actividad->descripcion);
        $this->assertSame(Actividad::SECCION_USUARIO, $actividad->seccion);
    }

    public function test_las_exportaciones_quedan_registradas(): void
    {
        $this->actingAs($this->socio)->get('/multas/exportar')->assertOk();

        $actividad = Actividad::sole();
        $this->assertSame('exportacion', $actividad->accion);
        $this->assertSame(Actividad::SECCION_USUARIO, $actividad->seccion);
        $this->assertSame('Exportó sus multas a Excel', $actividad->descripcion);
    }

    // ---------- Consulta y filtros ----------

    public function test_filtra_por_apartado_mes_y_anio(): void
    {
        Actividad::factory()->enFecha('2026-03-10 10:00:00')->create(['descripcion' => 'admin marzo']);
        Actividad::factory()->enFecha('2026-04-02 10:00:00')->create(['descripcion' => 'admin abril']);
        Actividad::factory()->enFecha('2025-03-10 10:00:00')->create(['descripcion' => 'admin marzo 2025']);
        Actividad::factory()->deUsuario()->enFecha('2026-03-15 10:00:00')->create(['descripcion' => 'socio marzo']);

        $descripciones = fn (string $url) => $this->actingAs($this->admin)->get($url)->assertOk()->viewData('actividades')->pluck('descripcion')->all();

        $this->assertSame(['admin marzo'], $descripciones('/historial?gestion=2026&mes=3'));
        $this->assertSame(['socio marzo'], $descripciones('/historial?seccion=usuario&gestion=2026&mes=3'));
        $this->assertSame(['admin abril', 'admin marzo'], $descripciones('/historial?gestion=2026&mes=todos'));
        $this->assertSame(['admin marzo 2025'], $descripciones('/historial?gestion=2025&mes=3'));

        // El último día del mes a las 23:59 entra en ese mes
        Actividad::factory()->enFecha('2026-03-31 23:59:30')->create(['descripcion' => 'fin de marzo']);
        $this->assertContains('fin de marzo', $descripciones('/historial?gestion=2026&mes=3'));
    }

    public function test_por_defecto_muestra_el_mes_actual_y_pagina(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        Actividad::factory()->count(config('historial.por_pagina') + 5)->create();
        Actividad::factory()->enFecha('2026-09-30 12:00:00')->create();

        $this->actingAs($this->admin)->get('/historial')
            ->assertViewHas('filtros', fn ($f) => $f['gestion'] === 2026 && $f['mes'] === 10)
            ->assertViewHas('actividades', fn ($p) => $p->total() === config('historial.por_pagina') + 5
                && $p->count() === config('historial.por_pagina'));
    }

    public function test_filtra_por_modulo_accion_y_texto(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        Actividad::factory()->create(['modulo' => 'multas', 'accion' => 'pago', 'descripcion' => 'Registró el pago de la multa de Ana']);
        Actividad::factory()->create(['modulo' => 'lecturas', 'accion' => 'creado', 'usuario_nombre' => 'Luis']);

        $total = fn (string $url) => $this->actingAs($this->admin)->get($url)->viewData('actividades')->total();

        $this->assertSame(1, $total('/historial?modulo=multas'));
        $this->assertSame(1, $total('/historial?accion=creado'));
        $this->assertSame(1, $total('/historial?buscar=Ana'));
        $this->assertSame(1, $total('/historial?buscar=luis'));
    }

    // ---------- Permisos ----------

    public function test_el_socio_no_puede_ver_el_historial(): void
    {
        $this->actingAs($this->socio)->get('/historial')->assertForbidden();
        $this->actingAs($this->socio)->get('/historial/exportar')->assertForbidden();
    }

    public function test_solo_el_superadmin_puede_borrar(): void
    {
        $actividad = Actividad::factory()->create();

        $this->actingAs($this->admin)->get('/historial')->assertOk()->assertDontSee('Eliminar registros de un periodo');
        $this->actingAs($this->admin)->delete("/historial/{$actividad->id}")->assertForbidden();
        $this->actingAs($this->admin)->delete('/historial/periodo', ['seccion' => 'admin', 'gestion' => date('Y')])->assertForbidden();
        $this->actingAs($this->socio)->delete("/historial/{$actividad->id}")->assertForbidden();

        $this->assertModelExists($actividad);

        $this->actingAs($this->superadmin)->get('/historial')->assertSee('Eliminar registros de un periodo');
        $this->actingAs($this->superadmin)->delete("/historial/{$actividad->id}");
        $this->assertModelMissing($actividad);

        // Borrar del historial no genera un registro nuevo
        $this->assertSame(0, Actividad::count());
    }

    public function test_el_superadmin_borra_un_periodo_completo_de_un_apartado(): void
    {
        Actividad::factory()->count(3)->enFecha('2025-03-10 10:00:00')->create();
        Actividad::factory()->enFecha('2025-04-10 10:00:00')->create();
        Actividad::factory()->deUsuario()->enFecha('2025-03-10 10:00:00')->create();

        $this->actingAs($this->superadmin)->delete('/historial/periodo', ['seccion' => 'admin', 'gestion' => 2025, 'mes' => 3])
            ->assertSessionHas('success', 'Se eliminaron 3 registro(s) del historial.');

        // Quedan abril (admin) y marzo (usuarios); el borrado no deja registro propio
        $this->assertSame(1, Actividad::seccion('admin')->periodo(2025, 4)->count());
        $this->assertSame(1, Actividad::seccion('usuario')->periodo(2025, 3)->count());
        $this->assertSame(2, Actividad::count());
    }

    public function test_la_depuracion_automatica_solo_actua_si_se_configura(): void
    {
        $this->travelTo('2026-10-15');
        $vieja = Actividad::factory()->enFecha('2022-01-10 10:00:00')->create();
        $reciente = Actividad::factory()->enFecha('2026-09-10 10:00:00')->create();

        $this->artisan('model:prune', ['--model' => [Actividad::class]]);
        $this->assertModelExists($vieja);

        config(['historial.meses_retencion' => 24]);
        $this->artisan('model:prune', ['--model' => [Actividad::class]]);
        $this->assertModelMissing($vieja);
        $this->assertModelExists($reciente);
    }

    public function test_exportar_historial_a_excel(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        Actividad::factory()->create(['descripcion' => 'Registró una lectura de prueba']);

        $csv = $this->actingAs($this->admin)->get('/historial/exportar')->assertOk()->streamedContent();

        $this->assertStringContainsString('"Fecha y hora";Usuario;Rol;Acción;Módulo;Descripción;IP', $csv);
        $this->assertStringContainsString('"Registró una lectura de prueba"', $csv);
        $this->assertStringNotContainsString('Exportó el historial', $csv);
    }

    public function test_la_pantalla_funciona_en_celular_y_en_balances(): void
    {
        BalanceMensual::factory()->create();
        $this->actingAs($this->superadmin)->post('/tarifas-multas', ['nombre' => 'Daño a la red', 'monto_predeterminado' => 150]);

        $html = $this->actingAs($this->superadmin)->get('/historial')->assertOk()
            ->assertSee('la tarifa «Daño a la red» (Bs. 150,00)', false)
            ->getContent();

        $this->assertStringContainsString('class="tabla-responsiva', $html);
        $this->assertStringContainsString('data-label="Descripción"', $html);
    }
}
