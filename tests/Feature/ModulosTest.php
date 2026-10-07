<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Ajuste;
use App\Models\BalanceMensual;
use App\Models\Lectura;
use App\Models\Multa;
use App\Models\TarifaMulta;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Database\Seeders\TarifaMultaSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ModulosTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private User $admin;

    private User $socio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolSeeder::class, TarifaMultaSeeder::class]);

        $this->superadmin = User::factory()->create(['rol_id' => RolUsuario::SuperAdmin->value]);
        $this->admin = User::factory()->create(['rol_id' => RolUsuario::Admin->value]);
        $this->socio = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Socio Uno']);
    }

    public function test_todas_las_pantallas_se_renderizan(): void
    {
        $lectura = Lectura::factory()->for($this->socio, 'usuario')->create();
        $balance = BalanceMensual::factory()->create();
        $multa = Multa::factory()->for($this->socio, 'socio')->create();

        $paginasAdmin = [
            '/lecturas', '/lecturas/crear', "/lecturas/{$lectura->id}/editar",
            '/finanzas', '/finanzas/crear', "/finanzas/{$balance->id}/editar",
            '/multas', '/multas/crear', "/multas/{$multa->id}/editar",
            '/tarifas-multas', '/reportes', '/reportes?mes=3',
        ];
        foreach ($paginasAdmin as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        foreach (['/usuarios', '/usuarios/crear', "/usuarios/{$this->socio->id}/editar"] as $url) {
            $this->actingAs($this->superadmin)->get($url)->assertOk();
        }

        foreach (['/mi-consumo', '/finanzas', '/multas'] as $url) {
            $this->actingAs($this->socio)->get($url)->assertOk();
        }
    }

    public function test_la_lectura_calcula_consumo_y_monto_con_la_tarifa_configurada(): void
    {
        Ajuste::where('clave', Ajuste::TARIFA_M3)->update(['valor' => '3.50']);

        $this->actingAs($this->admin)->post('/lecturas', [
            'user_id' => $this->socio->id,
            'mes' => 3,
            'gestion' => 2026,
            'lectura_anterior' => 100,
            'lectura_actual' => 110,
        ])->assertRedirect(route('lecturas.index'));

        $lectura = Lectura::sole();
        $this->assertEquals(10, $lectura->consumo);
        $this->assertEquals(35.0, $lectura->monto);
        $this->assertSame('Marzo / 2026', $lectura->periodo);
    }

    public function test_la_lectura_actual_no_puede_ser_menor_que_la_anterior(): void
    {
        $this->actingAs($this->admin)->post('/lecturas', [
            'user_id' => $this->socio->id,
            'mes' => 3,
            'gestion' => 2026,
            'lectura_anterior' => 100,
            'lectura_actual' => 90,
        ])->assertSessionHasErrors('lectura_actual');
    }

    public function test_confirmar_pago_de_lectura(): void
    {
        $lectura = Lectura::factory()->for($this->socio, 'usuario')->create();

        $this->actingAs($this->admin)->patch("/lecturas/{$lectura->id}/pagar", ['metodo_pago' => 'qr']);

        $this->assertSame(Lectura::PAGADO, $lectura->fresh()->estado);
    }

    public function test_balance_calcula_saldo_y_filtra_por_mes(): void
    {
        $this->actingAs($this->admin)->post('/finanzas', [
            'mes' => 3, 'gestion' => 2026, 'ingresos' => 500, 'egresos' => 120,
        ])->assertRedirect(route('finanzas.index'));

        $this->assertEquals(380, BalanceMensual::sole()->saldo_final);

        BalanceMensual::factory()->create(['mes' => 7, 'gestion' => 2026]);

        // Acepta el número o el nombre del mes (filtros guardados antes del cambio)
        foreach (['3', 'Marzo'] as $filtro) {
            $this->actingAs($this->socio)->get("/finanzas?mes={$filtro}")
                ->assertViewHas('balances', fn ($balances) => $balances->pluck('mes')->all() === [3]);
        }
    }

    public function test_solo_el_superadmin_elimina_balances(): void
    {
        $balance = BalanceMensual::factory()->create();

        $this->actingAs($this->admin)->delete("/finanzas/{$balance->id}")->assertForbidden();
        $this->actingAs($this->superadmin)->delete("/finanzas/{$balance->id}");

        $this->assertModelMissing($balance);
    }

    public function test_el_socio_solo_ve_sus_multas_y_sus_totales(): void
    {
        Multa::factory()->for($this->socio, 'socio')->create(['monto' => 30]);
        Multa::factory()->create(['monto' => 500]);

        $this->actingAs($this->socio)->get('/multas')
            ->assertViewHas('multas', fn ($multas) => $multas->total() === 1)
            ->assertViewHas('totalPendiente', fn ($total) => (float) $total === 30.0);
    }

    public function test_registro_masivo_de_multas(): void
    {
        $otroSocio = User::factory()->create(['rol_id' => RolUsuario::Socio->value]);
        $tarifa = TarifaMulta::first();

        $this->actingAs($this->admin)->post('/multas', [
            'tipo_registro' => 'masivo',
            'socios' => [$this->socio->id, $otroSocio->id],
            'tarifa_multa_id' => $tarifa->id,
            'monto' => $tarifa->monto_predeterminado,
            'fecha_multa' => '2026-05-01',
        ])->assertRedirect(route('multas.index'));

        $this->assertSame(2, Multa::where('tipo_multa', $tarifa->nombre)->count());
    }

    public function test_reporte_filtra_por_gestion_y_ordena_morosos(): void
    {
        $otroSocio = User::factory()->create(['rol_id' => RolUsuario::Socio->value]);

        // 10 m³ * 2 Bs = 20 Bs pendientes en 2026
        Lectura::factory()->for($this->socio, 'usuario')->create([
            'gestion' => 2026, 'mes' => 1, 'lectura_anterior' => 0, 'lectura_actual' => 10,
        ]);
        Multa::factory()->for($otroSocio, 'socio')->create(['monto' => 80, 'fecha_multa' => '2026-02-10']);
        // De otra gestión: no debe aparecer
        Lectura::factory()->for($this->socio, 'usuario')->create(['gestion' => 2025, 'lectura_anterior' => 0, 'lectura_actual' => 999]);
        // Consumo cero: no debe inventar un monto
        Lectura::factory()->for($this->socio, 'usuario')->create([
            'gestion' => 2026, 'mes' => 2, 'lectura_anterior' => 5, 'lectura_actual' => 5,
        ]);

        $this->actingAs($this->admin)->get('/reportes?gestion=2026')
            ->assertViewHas('totalGeneralPorCobrar', 100.0)
            ->assertViewHas('sociosMorosos', fn ($morosos) => $morosos->pluck('id')->all() === [$otroSocio->id, $this->socio->id]);

        $this->actingAs($this->admin)->get('/reportes?gestion=2026&mes=1')
            ->assertViewHas('totalGeneralPorCobrar', 20.0);
    }

    public function test_exportaciones_csv(): void
    {
        Lectura::factory()->for($this->socio, 'usuario')->create(['mes' => 4]);
        Multa::factory()->for($this->socio, 'socio')->create();
        BalanceMensual::factory()->create(['mes' => 4]);

        foreach (['/lecturas/exportar', '/finanzas/exportar', '/multas/exportar', '/reportes/exportar'] as $url) {
            $csv = $this->actingAs($this->admin)->get($url)->assertOk()->streamedContent();

            $this->assertStringStartsWith("\xEF\xBB\xBFsep=;\n", $csv, $url);
            if ($url !== '/finanzas/exportar') {
                $this->assertStringContainsString('Socio Uno', $csv, $url);
            }
        }

        $this->assertStringContainsString('Abril', $this->actingAs($this->admin)->get('/lecturas/exportar')->streamedContent());
        $this->assertStringContainsString('Socio Uno', $this->actingAs($this->superadmin)->get('/usuarios/exportar')->streamedContent());
    }

    public function test_editar_usuario_sin_contrasena_conserva_la_actual(): void
    {
        $this->socio->update(['password' => 'clave-original']);

        $this->actingAs($this->superadmin)->put("/usuarios/{$this->socio->id}", [
            'name' => 'Nombre Nuevo',
            'email' => $this->socio->email,
            'password' => '',
            'rol_id' => RolUsuario::Socio->value,
        ])->assertRedirect(route('usuarios.index'));

        $socio = $this->socio->fresh();
        $this->assertSame('Nombre Nuevo', $socio->name);
        $this->assertTrue(Hash::check('clave-original', $socio->password));
    }

    public function test_crear_usuario_encripta_la_contrasena(): void
    {
        $this->actingAs($this->superadmin)->post('/usuarios', [
            'name' => 'Nuevo Socio',
            'email' => 'nuevo@otb.com',
            'password' => 'secreto123',
            'rol_id' => RolUsuario::Socio->value,
        ])->assertRedirect(route('usuarios.index'));

        $this->assertTrue(Hash::check('secreto123', User::where('email', 'nuevo@otb.com')->value('password')));
    }

    public function test_crud_de_tarifas(): void
    {
        $this->actingAs($this->admin)->post('/tarifas-multas', [
            'nombre' => 'Daño a la red', 'monto_predeterminado' => 150,
        ])->assertRedirect(route('tarifas-multas.index'));

        $tarifa = TarifaMulta::where('nombre', 'Daño a la red')->sole();
        $this->assertEquals(150, $tarifa->monto_predeterminado);
    }

    public function test_dar_de_baja_a_un_socio_conserva_su_historial(): void
    {
        $lectura = Lectura::factory()->for($this->socio, 'usuario')->create(['gestion' => 2026, 'lectura_anterior' => 0, 'lectura_actual' => 10]);
        $multa = Multa::factory()->for($this->socio, 'socio')->create(['fecha_multa' => '2026-03-01']);

        $this->actingAs($this->superadmin)->delete("/usuarios/{$this->socio->id}")
            ->assertRedirect(route('usuarios.index'));

        $this->assertSoftDeleted($this->socio);
        $this->assertModelExists($lectura);
        $this->assertModelExists($multa);

        // Sigue apareciendo con su nombre en lecturas y en la lista de morosos
        $this->actingAs($this->admin)->get('/lecturas')->assertSee('Socio Uno');
        $this->actingAs($this->admin)->get('/reportes?gestion=2026')
            ->assertViewHas('sociosMorosos', fn ($morosos) => $morosos->pluck('id')->contains($this->socio->id));

        // Ya no aparece para nuevas lecturas ni multas
        $this->actingAs($this->admin)->get('/multas/crear')
            ->assertViewHas('socios', fn ($socios) => ! $socios->contains('id', $this->socio->id));
    }

    public function test_un_socio_dado_de_baja_no_puede_iniciar_sesion(): void
    {
        $this->socio->delete();

        $this->post('/login', ['email' => $this->socio->email, 'password' => 'password']);

        $this->assertGuest();
    }

    public function test_la_base_impide_borrar_fisicamente_a_un_socio_con_historial(): void
    {
        Lectura::factory()->for($this->socio, 'usuario')->create();

        $this->expectException(QueryException::class);

        $this->socio->forceDelete();
    }

    public function test_los_comprobantes_se_guardan_en_privado_y_requieren_sesion(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->actingAs($this->admin)->post('/finanzas', [
            'mes' => 3, 'gestion' => 2026, 'ingresos' => 100, 'egresos' => 0,
            'comprobante' => UploadedFile::fake()->create('recibo.pdf', 10, 'application/pdf'),
        ]);

        $balance = BalanceMensual::sole();
        Storage::disk('local')->assertExists($balance->comprobante_url);
        Storage::disk('public')->assertMissing($balance->comprobante_url);

        // Sin sesión: redirige al login
        auth()->logout();
        $this->get("/finanzas/{$balance->id}/comprobante")->assertRedirect(route('login'));

        // Con sesión (cualquier rol, igual que la vista de Finanzas)
        $this->actingAs($this->socio)->get("/finanzas/{$balance->id}/comprobante")->assertOk();
        $this->actingAs($this->socio)->get("/finanzas/{$balance->id}/comprobante?descargar=1")
            ->assertDownload('comprobante_Marzo_2026.pdf');
    }

    public function test_balance_sin_comprobante_responde_404(): void
    {
        $balance = BalanceMensual::factory()->create();

        $this->actingAs($this->admin)->get("/finanzas/{$balance->id}/comprobante")->assertNotFound();
    }

    public function test_el_formulario_de_multas_usa_las_tarifas_de_la_base(): void
    {
        $tarifa = TarifaMulta::create(['nombre' => 'Daño a la red', 'monto_predeterminado' => 150]);

        $this->actingAs($this->admin)->get('/multas/crear')
            ->assertSee('value="'.$tarifa->id.'" data-monto="150.00"', false);
    }

    public function test_la_multa_toma_el_nombre_de_su_tarifa_real(): void
    {
        $desfile = TarifaMulta::where('nombre', 'Inasistencia a Desfile Cívico')->sole();

        // Aunque el navegador mande otro texto, manda la tarifa elegida
        $this->actingAs($this->admin)->post('/multas', [
            'user_id' => $this->socio->id,
            'tarifa_multa_id' => $desfile->id,
            'tipo_multa_texto' => 'Inasistencia a Reunión / Asamblea',
            'monto' => 50,
            'fecha_multa' => '2026-05-01',
        ]);

        $multa = Multa::sole();
        $this->assertSame($desfile->id, $multa->tarifa_multa_id);
        $this->assertSame('Inasistencia a Desfile Cívico', $multa->tipo_multa);
    }

    public function test_multa_otro_usa_el_motivo_y_una_multa_antigua_conserva_su_nombre(): void
    {
        $this->actingAs($this->admin)->post('/multas', [
            'user_id' => $this->socio->id,
            'tarifa_multa_id' => 'otro',
            'motivo' => 'Rotura de medidor',
            'monto' => 200,
            'fecha_multa' => '2026-05-01',
        ]);
        $this->assertSame('Rotura de medidor', Multa::sole()->tipo_multa);

        // Multa registrada antes del cambio, sin tarifa ligada
        $antigua = Multa::factory()->for($this->socio, 'socio')->create(['tipo_multa' => 'Inasistencia a Trabajo Comunal']);

        $this->actingAs($this->admin)->put("/multas/{$antigua->id}", [
            'user_id' => $this->socio->id,
            'tarifa_multa_id' => 'otro',
            'tipo_multa_texto' => 'Inasistencia a Trabajo Comunal',
            'monto' => 80,
            'fecha_multa' => '2026-05-01',
            'estado' => Multa::CONDONADO,
        ])->assertSessionHasNoErrors();

        $antigua->refresh();
        $this->assertSame('Inasistencia a Trabajo Comunal', $antigua->tipo_multa);
        $this->assertSame(Multa::CONDONADO, $antigua->estado);
    }
}
