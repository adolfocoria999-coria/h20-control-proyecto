<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Actividad;
use App\Models\Comunicado;
use App\Models\SuscripcionPush;
use App\Models\User;
use App\Services\NotificacionPush;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificacionesDirectivaTest extends TestCase
{
    use RefreshDatabase;

    private User $presidente;

    private User $hacienda;

    private User $juan;

    private User $rosa;

    private User $plomero;

    /** @var array<string, array> */
    private array $enviadas = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        $this->presidente = User::factory()->create(['rol_id' => RolUsuario::SuperAdmin->value]);
        $this->hacienda = User::factory()->create(['rol_id' => RolUsuario::Admin->value]);
        $this->juan = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Juan Pérez']);
        $this->rosa = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Rosa Condori']);
        $this->plomero = User::factory()->create(['rol_id' => RolUsuario::Plomero->value]);

        // Sin red: un envío falso que registra lo que se mandaría
        $prueba = $this;
        $this->app->instance(NotificacionPush::class, new class($prueba) extends NotificacionPush
        {
            public function __construct(private $prueba) {}

            protected function despachar(array $envios): array
            {
                foreach ($envios as [$suscripcion, $contenido]) {
                    $this->prueba->registrarEnvio($suscripcion->endpoint, json_decode($contenido, true));
                }

                return [];
            }
        });
        $this->withoutDefer();
    }

    public function registrarEnvio(string $endpoint, array $contenido): void
    {
        $this->enviadas[$endpoint] = $contenido;
    }

    private function enviar(User $quien, array $datos = [])
    {
        return $this->actingAs($quien)->post('/notificaciones', $datos + [
            'titulo' => 'Asamblea general',
            'mensaje' => "El sábado a las 9:00 en la sede.\nAsistencia obligatoria.",
        ]);
    }

    public function test_la_directiva_envia_un_mensaje_y_todos_los_socios_lo_ven(): void
    {
        $this->enviar($this->hacienda)->assertRedirect('/notificaciones')->assertSessionHas('success');

        $comunicado = Comunicado::sole();
        $this->assertSame($this->hacienda->id, $comunicado->user_id);
        $this->assertFalse($comunicado->importante);

        foreach ([$this->juan, $this->rosa] as $socio) {
            $this->actingAs($socio)->get('/notificaciones')->assertOk()
                ->assertSee('Asamblea general')->assertSee('Asistencia obligatoria.')
                ->assertDontSee('Enviar a todos los socios');
        }

        $this->actingAs($this->presidente)->get('/notificaciones')->assertOk()->assertSee('Enviar a todos los socios');
        $this->enviar($this->presidente, ['titulo' => 'Corte de agua', 'importante' => '1'])->assertRedirect('/notificaciones');
        $this->assertTrue(Comunicado::where('titulo', 'Corte de agua')->sole()->importante);

        $this->assertSame(2, Actividad::where('modulo', 'mensajes')->count());
    }

    public function test_solo_la_directiva_envia_o_elimina_y_el_plomero_no_entra(): void
    {
        $this->enviar($this->juan)->assertForbidden();
        $this->enviar($this->plomero)->assertForbidden();
        $this->actingAs($this->plomero)->get('/notificaciones')->assertForbidden();
        $this->assertSame(0, Comunicado::count());

        $this->enviar($this->hacienda);
        $comunicado = Comunicado::sole();
        $this->actingAs($this->juan)->delete("/notificaciones/{$comunicado->id}")->assertForbidden();
        $this->actingAs($this->presidente)->delete("/notificaciones/{$comunicado->id}")->assertRedirect('/notificaciones');
        $this->assertSame(0, Comunicado::count());
    }

    public function test_validaciones(): void
    {
        $this->enviar($this->hacienda, ['titulo' => '', 'mensaje' => ''])->assertSessionHasErrors(['titulo', 'mensaje']);
        $this->enviar($this->hacienda, ['titulo' => str_repeat('a', 121)])->assertSessionHasErrors('titulo');
        $this->enviar($this->hacienda, ['mensaje' => str_repeat('a', 2001)])->assertSessionHasErrors('mensaje');
        $this->assertSame(0, Comunicado::count());
    }

    public function test_el_menu_muestra_los_mensajes_sin_leer_hasta_que_se_abren(): void
    {
        $this->travelTo(now()->subMinute());
        $this->enviar($this->hacienda);
        $this->enviar($this->hacienda, ['titulo' => 'Faena del domingo']);
        $this->travelBack();

        $this->assertSame(2, Comunicado::sinLeer($this->juan));
        $this->assertSame(0, Comunicado::sinLeer($this->hacienda)); // los suyos no cuentan

        $this->actingAs($this->juan)->get('/mi-consumo')->assertSee('title="Notificaciones sin leer"', false);
        $this->actingAs($this->juan)->get('/notificaciones')->assertSee('Nuevo');

        $this->assertSame(0, Comunicado::sinLeer($this->juan->fresh()));
        $this->assertSame(2, Comunicado::sinLeer($this->rosa));
        // Abrir Mensajes no ensucia el historial
        $this->assertSame(0, Actividad::where('modulo', 'usuarios')->where('user_id', $this->juan->id)->count());
    }

    public function test_llega_una_notificacion_al_celular_de_los_socios(): void
    {
        foreach (['juan' => $this->juan, 'rosa' => $this->rosa, 'hacienda' => $this->hacienda] as $nombre => $usuario) {
            SuscripcionPush::create(['user_id' => $usuario->id, 'endpoint' => "https://push.test/{$nombre}",
                'endpoint_hash' => SuscripcionPush::hash("https://push.test/{$nombre}"), 'clave_p256dh' => 'x', 'clave_auth' => 'y']);
        }

        $this->enviar($this->hacienda, ['titulo' => 'Corte de agua', 'importante' => '1']);

        $this->assertEqualsCanonicalizing(['https://push.test/juan', 'https://push.test/rosa'], array_keys($this->enviadas));
        $this->assertSame('⚠️ Corte de agua', $this->enviadas['https://push.test/juan']['title']);
        $this->assertStringContainsString('El sábado a las 9:00', $this->enviadas['https://push.test/juan']['body']);
        $this->assertSame(route('mensajes.index'), $this->enviadas['https://push.test/juan']['url']);
    }

    public function test_el_texto_del_mensaje_se_muestra_escapado(): void
    {
        $this->enviar($this->hacienda, ['mensaje' => '<script>alert(1)</script> reunión']);

        $this->actingAs($this->juan)->get('/notificaciones')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }
}
