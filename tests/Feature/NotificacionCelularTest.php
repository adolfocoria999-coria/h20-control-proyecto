<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Ajuste;
use App\Models\Lectura;
use App\Models\SuscripcionPush;
use App\Models\User;
use App\Services\NotificacionPush;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificacionCelularTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $juan;

    private User $rosa;

    /** Notificaciones que se habrían entregado: [endpoint => contenido] */
    private array $enviadas = [];

    /** Endpoints que el servicio de push informa como vencidos */
    private array $vencidos = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        Ajuste::guardar(Ajuste::TARIFA_M3, '2.00');
        $this->admin = User::factory()->create(['rol_id' => RolUsuario::Admin->value]);
        $this->juan = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Juan Pérez']);
        $this->rosa = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Rosa Condori']);

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

                return array_values(array_intersect($this->prueba->vencidos(), array_map(fn ($e) => $e[0]->endpoint, $envios)));
            }
        });
        $this->withoutDefer(); // los avisos "después de responder" se ejecutan en el acto
    }

    public function registrarEnvio(string $endpoint, array $contenido): void
    {
        $this->enviadas[$endpoint] = $contenido;
    }

    public function vencidos(): array
    {
        return $this->vencidos;
    }

    private function suscribir(User $usuario, string $endpoint)
    {
        return $this->actingAs($usuario)->postJson('/notificaciones/suscripcion', [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'BClavePublicaDelNavegador', 'auth' => 'secreto-auth'],
        ]);
    }

    public function test_el_socio_activa_y_desactiva_los_avisos_de_su_celular(): void
    {
        $this->suscribir($this->juan, 'https://fcm.googleapis.com/fcm/send/abc')->assertOk()->assertJson(['activadas' => true]);
        $this->suscribir($this->juan, 'https://fcm.googleapis.com/fcm/send/abc')->assertOk(); // el mismo celular no se duplica

        $this->assertSame(1, SuscripcionPush::count());
        $this->assertSame($this->juan->id, SuscripcionPush::sole()->user_id);
        $this->assertDatabaseHas('actividades', ['descripcion' => 'Activó las notificaciones en su celular', 'user_id' => $this->juan->id]);

        // Solo direcciones https de un servicio de push
        $this->suscribir($this->juan, 'http://inseguro.test/x')->assertUnprocessable();

        $this->actingAs($this->juan)->deleteJson('/notificaciones/suscripcion', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc'])
            ->assertOk();
        $this->assertSame(0, SuscripcionPush::count());
    }

    public function test_al_registrar_la_lectura_le_llega_el_aviso_solo_a_quien_lo_activo(): void
    {
        $this->suscribir($this->juan, 'https://fcm.googleapis.com/fcm/send/juan');
        $this->suscribir($this->juan, 'https://web.push.apple.com/juan-ipad');   // dos dispositivos
        // Rosa no activó los avisos

        $this->actingAs($this->admin)->post('/lecturas/planilla', [
            'mes' => 10, 'gestion' => 2026,
            'lecturas' => [
                $this->juan->id => ['actual' => '15', 'anterior' => '0'],
                $this->rosa->id => ['actual' => '8', 'anterior' => '0'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertCount(2, $this->enviadas);
        $aviso = $this->enviadas['https://fcm.googleapis.com/fcm/send/juan'];
        $this->assertSame('💧 Tu lectura de Octubre 2026', $aviso['title']);
        $this->assertSame('Consumo de 15 m³ · Bs. 30,00. Toca para ver el detalle y pagar.', $aviso['body']);
        $this->assertSame(route('socio.consumo'), $aviso['url']);
    }

    public function test_tambien_avisa_al_registrar_una_lectura_suelta(): void
    {
        $this->suscribir($this->rosa, 'https://fcm.googleapis.com/fcm/send/rosa');

        $this->actingAs($this->admin)->post('/lecturas', [
            'user_id' => $this->rosa->id, 'mes' => 10, 'gestion' => 2026, 'lectura_anterior' => 100, 'lectura_actual' => 110,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Consumo de 10 m³ · Bs. 20,00. Toca para ver el detalle y pagar.', $this->enviadas['https://fcm.googleapis.com/fcm/send/rosa']['body']);
    }

    public function test_un_celular_que_ya_no_existe_se_borra(): void
    {
        $this->suscribir($this->juan, 'https://fcm.googleapis.com/fcm/send/viejo');
        $this->vencidos = ['https://fcm.googleapis.com/fcm/send/viejo'];

        $this->actingAs($this->juan)->postJson('/notificaciones/prueba')->assertUnprocessable();

        $this->assertSame(0, SuscripcionPush::count());
    }

    public function test_probar_manda_un_aviso_al_propio_celular(): void
    {
        $this->suscribir($this->juan, 'https://fcm.googleapis.com/fcm/send/juan');

        $this->actingAs($this->juan)->postJson('/notificaciones/prueba')->assertOk()->assertJson(['enviadas' => 1]);
        $this->assertSame('🔔 Notificaciones activadas', $this->enviadas['https://fcm.googleapis.com/fcm/send/juan']['title']);
    }

    public function test_si_falla_el_envio_igual_se_guardan_las_lecturas(): void
    {
        $this->app->instance(NotificacionPush::class, new class extends NotificacionPush
        {
            protected function despachar(array $envios): array
            {
                throw new \RuntimeException('Sin conexión con el servicio de push');
            }
        });
        $this->suscribir($this->juan, 'https://fcm.googleapis.com/fcm/send/juan');

        $this->actingAs($this->admin)->post('/lecturas/planilla', [
            'mes' => 10, 'gestion' => 2026, 'lecturas' => [$this->juan->id => ['actual' => '15', 'anterior' => '0']],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Lectura::count());
    }

    public function test_la_tarjeta_de_avisos_aparece_en_mi_consumo_y_en_el_perfil(): void
    {
        $this->actingAs($this->juan)->get('/mi-consumo')->assertSee('Avisos en este celular')->assertSee(config('services.webpush.public_key'));
        $this->actingAs($this->admin)->get('/profile')->assertSee('Avisos en este celular');
        $this->assertStringContainsString("addEventListener('push'", file_get_contents(public_path('sw.js')));
    }
}
