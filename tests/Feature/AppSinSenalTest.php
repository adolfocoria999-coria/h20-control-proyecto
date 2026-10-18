<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Lectura;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * App instalable y envío de lecturas anotadas sin señal: la planilla las guarda
 * en el celular y las manda después en segundo plano (JSON).
 */
class AppSinSenalTest extends TestCase
{
    use RefreshDatabase;

    private User $tecnico;

    private User $ana;

    private User $beto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        $this->tecnico = User::factory()->create(['rol_id' => RolUsuario::Tecnico->value]);
        $this->ana = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Ana Quispe']);
        $this->beto = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Beto Mamani']);
        foreach ([[$this->ana, 110], [$this->beto, 520]] as [$socio, $actual]) {
            Lectura::factory()->for($socio, 'usuario')
                ->create(['mes' => 9, 'gestion' => 2026, 'lectura_anterior' => $actual - 10, 'lectura_actual' => $actual]);
        }
    }

    private function enviarDesdeElCelular(array $lecturas, int $mes = 10)
    {
        return $this->actingAs($this->tecnico)
            ->postJson('/lecturas/planilla', ['mes' => $mes, 'gestion' => 2026, 'lecturas' => $lecturas]);
    }

    public function test_los_archivos_de_la_app_instalable_existen_y_se_enlazan(): void
    {
        $manifiesto = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);
        $this->assertSame('H2O Control', $manifiesto['name']);
        $this->assertSame('standalone', $manifiesto['display']);
        foreach ($manifiesto['icons'] as $icono) {
            $this->assertFileExists(public_path(ltrim($icono['src'], '/')));
        }
        $this->assertFileExists(public_path('sw.js'));
        $this->assertStringContainsString("'/lecturas/planilla'", file_get_contents(public_path('sw.js')));

        foreach (['/login', '/lecturas/planilla'] as $url) {
            $respuesta = $url === '/login' ? $this->get($url) : $this->actingAs($this->tecnico)->get($url);
            $respuesta->assertSee('manifest.webmanifest')->assertSee('sw.js');
        }
    }

    public function test_la_planilla_trae_el_guardado_en_el_celular_y_el_boton_instalar(): void
    {
        $this->actingAs($this->tecnico)->get('/lecturas/planilla?mes=10&gestion=2026')
            ->assertSee("'h2o-planilla:{$this->tecnico->id}:'", false)
            ->assertSee('id="estado-envio"', false)
            ->assertSee('Instala H2O Control en tu celular')
            ->assertSee('data-id="'.$this->ana->id.'"', false);
    }

    public function test_las_lecturas_guardadas_en_el_celular_se_envian_despues(): void
    {
        $this->enviarDesdeElCelular([$this->ana->id => ['actual' => '125'], $this->beto->id => ['actual' => '530']])
            ->assertOk()
            ->assertJson(['guardadas' => 2, 'mensaje' => '2 lectura(s) de Octubre 2026 registrada(s) ✓']);

        $this->assertEquals(15, Lectura::where(['user_id' => $this->ana->id, 'mes' => 10])->sole()->consumo);

        // Al recargar la planilla se ve el mensaje de éxito
        $this->get('/lecturas/planilla?mes=10&gestion=2026')->assertSee('2 lectura(s) de Octubre 2026 registrada(s)');
    }

    public function test_si_hacienda_ya_las_cargo_mientras_tanto_no_se_pisan(): void
    {
        Lectura::factory()->for($this->ana, 'usuario')
            ->create(['mes' => 10, 'gestion' => 2026, 'lectura_anterior' => 110, 'lectura_actual' => 118]);

        $this->enviarDesdeElCelular([$this->ana->id => ['actual' => '125'], $this->beto->id => ['actual' => '530']])
            ->assertOk()
            ->assertJson(['guardadas' => 1])
            ->assertJsonFragment(['mensaje' => '1 lectura(s) de Octubre 2026 registrada(s) ✓ Ya estaban registradas (no se cambiaron): Ana Quispe.']);
        $this->assertEquals(118, Lectura::where(['user_id' => $this->ana->id, 'mes' => 10])->sole()->lectura_actual);

        // Todas ya estaban: no es un error, se descartan del celular
        $this->enviarDesdeElCelular([$this->ana->id => ['actual' => '125']])
            ->assertOk()->assertJson(['guardadas' => 0]);
    }

    public function test_con_errores_responde_422_con_el_detalle_y_no_guarda_nada(): void
    {
        $respuesta = $this->enviarDesdeElCelular([$this->ana->id => ['actual' => '125'], $this->beto->id => ['actual' => '500']])
            ->assertStatus(422);

        $this->assertSame(
            'Beto Mamani: la lectura actual no puede ser menor que la anterior (520 m³).',
            $respuesta->json('errores')["lecturas.{$this->beto->id}.actual"]
        );
        $this->assertSame(0, Lectura::where('mes', 10)->count());
    }

    public function test_con_la_sesion_vencida_responde_401_para_que_el_celular_las_conserve(): void
    {
        $this->postJson('/lecturas/planilla', ['mes' => 10, 'gestion' => 2026, 'lecturas' => [$this->ana->id => ['actual' => '125']]])
            ->assertUnauthorized();

        $this->assertSame(0, Lectura::where('mes', 10)->count());
    }
}
