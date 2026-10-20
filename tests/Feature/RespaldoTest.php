<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Ajuste;
use App\Models\Lectura;
use App\Models\Multa;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RespaldoTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private User $admin;

    private User $socio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        $this->superadmin = User::factory()->create(['rol_id' => RolUsuario::SuperAdmin->value, 'name' => 'Presidente', 'email' => 'presi@otb.test']);
        $this->admin = User::factory()->create(['rol_id' => RolUsuario::Admin->value, 'name' => 'Hacienda']);
        $this->socio = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Ana Quispe']);
    }

    // El archivo descargado (el servidor lo borra después de enviarlo)
    private function contenido(TestResponse $respuesta): string
    {
        return file_get_contents($respuesta->baseResponse->getFile()->getPathname());
    }

    private function descargar(User $quien): TestResponse
    {
        return $this->actingAs($quien)->post('/respaldos/descargar', ['password' => 'password']);
    }

    private function restaurar(string $contenido, string $confirmacion = 'RESTAURAR'): TestResponse
    {
        return $this->actingAs($this->superadmin)->from('/respaldos')->post('/respaldos/restaurar', [
            'archivo' => UploadedFile::fake()->createWithContent('respaldo.json.gz', $contenido),
            'confirmacion' => $confirmacion,
            'password' => 'password',
        ]);
    }

    public function test_solo_la_directiva_entra_y_solo_el_superadmin_restaura(): void
    {
        $plomero = User::factory()->create(['rol_id' => RolUsuario::Plomero->value]);
        foreach ([$this->socio, $plomero] as $usuario) {
            $this->actingAs($usuario)->get('/respaldos')->assertForbidden();
            $this->actingAs($usuario)->post('/respaldos/descargar', ['password' => 'password'])->assertForbidden();
        }

        $this->actingAs($this->admin)->get('/respaldos')->assertOk()
            ->assertSee('Descargar respaldo')
            ->assertDontSee('action="'.route('respaldos.restaurar').'"', false); // sin formulario de restaurar
        $this->actingAs($this->admin)->post('/respaldos/restaurar', [])->assertForbidden();

        $this->actingAs($this->superadmin)->get('/respaldos')->assertOk()
            ->assertSee('Restaurar un respaldo')
            ->assertSee('Aún no descargaste ningún respaldo');
    }

    public function test_descargar_pide_la_contrasena(): void
    {
        $this->actingAs($this->admin)->from('/respaldos')->post('/respaldos/descargar', ['password' => 'otra'])
            ->assertSessionHasErrors(['password' => 'La contraseña no es correcta.']);

        $this->assertNull(Ajuste::valor('ultimo_respaldo'));
    }

    public function test_el_respaldo_trae_todo_lo_del_sistema(): void
    {
        Lectura::factory()->for($this->socio, 'usuario')->create(['mes' => 9, 'gestion' => 2026]);
        Multa::factory()->for($this->socio, 'socio')->create();
        Storage::disk('archivos')->put('qr/qr_otb.png', 'imagen-del-qr');
        DB::table('sessions')->insert(['id' => 'x', 'payload' => 'temporal', 'last_activity' => time()]);

        $respuesta = $this->descargar($this->admin)->assertOk();
        $this->assertMatchesRegularExpression('/respaldo_h2o_\d{4}-\d{2}-\d{2}_\d{4}\.json\.gz/', $respuesta->headers->get('content-disposition'));

        $datos = json_decode(gzdecode($this->contenido($respuesta)), true);
        $this->assertSame('h2o-control-respaldo', $datos['formato']);
        $this->assertSame('Hacienda', $datos['por']);
        $this->assertNotEmpty($datos['migraciones']);
        foreach (['users', 'rols', 'lecturas', 'multas', 'ajustes', 'archivos', 'actividades', 'balance_mensuals', 'tarifas_multas'] as $tabla) {
            $this->assertArrayHasKey($tabla, $datos['tablas'], "Falta la tabla {$tabla}");
        }
        $this->assertArrayNotHasKey('sessions', $datos['tablas']);
        $this->assertArrayNotHasKey('migrations', $datos['tablas']);
        $this->assertSame(3, $datos['conteos']['users']);
        $this->assertSame(1, $datos['conteos']['lecturas']);
        $this->assertSame(base64_encode('imagen-del-qr'), collect($datos['tablas']['archivos'])->firstWhere('ruta', 'qr/qr_otb.png')['contenido']);

        // Queda registrado y la pantalla lo muestra
        $this->assertStringEndsWith('|Hacienda', Ajuste::valor('ultimo_respaldo'));
        $this->assertDatabaseHas('actividades', ['modulo' => 'respaldos', 'accion' => 'exportacion', 'usuario_nombre' => 'Hacienda']);
        $this->actingAs($this->superadmin)->get('/respaldos')->assertSee('Último respaldo:')->assertSee('Descargó un respaldo completo');
    }

    public function test_restaurar_deja_todo_como_estaba_en_el_respaldo(): void
    {
        $lectura = Lectura::factory()->for($this->socio, 'usuario')->create(['mes' => 9, 'gestion' => 2026, 'lectura_anterior' => 100, 'lectura_actual' => 115]);
        Storage::disk('archivos')->put('qr/qr_otb.png', 'imagen-del-qr');
        Ajuste::guardar(Ajuste::TARIFA_M3, '2.00');
        $respaldo = $this->contenido($this->descargar($this->superadmin));

        // Después del respaldo pasan cosas...
        $lectura->delete();
        User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Socio Nuevo']);
        Ajuste::guardar(Ajuste::TARIFA_M3, '3.00');
        Storage::disk('archivos')->delete('qr/qr_otb.png');

        $this->restaurar($respaldo)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');
        $this->assertGuest();

        $this->assertEquals(15, Lectura::sole()->consumo);
        $this->assertFalse(User::where('name', 'Socio Nuevo')->exists());
        $this->assertSame('2.00', Ajuste::valor(Ajuste::TARIFA_M3));
        $this->assertSame('imagen-del-qr', Storage::disk('archivos')->get('qr/qr_otb.png'));
        $this->assertDatabaseHas('actividades', ['modulo' => 'respaldos', 'accion' => 'restaurado', 'usuario_nombre' => 'Presidente']);

        // Se puede volver a ingresar con los usuarios restaurados
        $this->post('/login', ['email' => 'presi@otb.test', 'password' => 'password']);
        $this->assertAuthenticated();
    }

    public function test_restaurar_exige_confirmar_y_un_archivo_valido(): void
    {
        $respaldo = $this->contenido($this->descargar($this->superadmin));

        $this->restaurar($respaldo, 'restaurar')->assertSessionHasErrors('confirmacion');
        $this->restaurar('no soy un respaldo')->assertSessionHasErrors(['archivo' => 'El archivo no es un respaldo de H2O Control (o está dañado).']);

        // De una versión más nueva del sistema
        $datos = json_decode(gzdecode($respaldo), true);
        $datos['migraciones'][] = '2099_01_01_000000_algo_del_futuro';
        $this->restaurar(gzencode(json_encode($datos)))
            ->assertSessionHasErrors(['archivo' => 'El respaldo es de una versión más nueva del sistema: actualiza el sistema antes de restaurarlo.']);

        $this->assertAuthenticatedAs($this->superadmin);
    }

    public function test_si_la_restauracion_falla_no_se_cambia_nada(): void
    {
        $respaldo = json_decode(gzdecode($this->contenido($this->descargar($this->superadmin))), true);
        Lectura::factory()->for($this->socio, 'usuario')->create(['mes' => 9, 'gestion' => 2026]);

        // Un usuario sin nombre (columna obligatoria) hace fallar la restauración a mitad de camino
        $respaldo['tablas']['users'][] = ['id' => 999, 'email' => 'roto@otb.test', 'name' => null, 'password' => 'x'];

        $this->restaurar(gzencode(json_encode($respaldo)))
            ->assertSessionHasErrors(['archivo' => 'No se pudo restaurar: el respaldo tiene datos que no encajan en esta base. No se cambió nada.']);

        $this->assertSame(1, Lectura::count());
        $this->assertSame(3, User::count());
        $this->assertAuthenticatedAs($this->superadmin);
    }

    public function test_respaldo_y_restauracion_desde_la_consola(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'h2o-test');
        Lectura::factory()->for($this->socio, 'usuario')->create(['mes' => 9, 'gestion' => 2026]);

        $this->artisan('respaldo:crear', ['ruta' => $ruta])->assertExitCode(0);
        Lectura::query()->delete();

        $this->artisan('respaldo:restaurar', ['archivo' => $ruta, '--force' => true])->assertExitCode(0);
        $this->assertSame(1, Lectura::count());

        unlink($ruta);
    }

    public function test_avisa_cuando_el_ultimo_respaldo_es_viejo(): void
    {
        Ajuste::guardar('ultimo_respaldo', now()->subDays(12)->toIso8601String().'|Hacienda');

        $this->actingAs($this->superadmin)->get('/respaldos')
            ->assertSee('hace 12 días')
            ->assertSee('Es buen momento para descargar uno nuevo');
    }
}
