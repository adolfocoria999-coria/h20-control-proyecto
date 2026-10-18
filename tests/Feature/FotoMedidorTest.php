<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Lectura;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FotoMedidorTest extends TestCase
{
    use RefreshDatabase;

    private User $plomero;

    private User $admin;

    private User $ana;

    private User $beto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        $this->plomero = User::factory()->create(['rol_id' => RolUsuario::Plomero->value]);
        $this->admin = User::factory()->create(['rol_id' => RolUsuario::Admin->value]);
        $this->ana = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Ana Quispe']);
        $this->beto = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Beto Mamani']);
    }

    // JPEG real chiquito (la validación "image" revisa el contenido)
    private function foto(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('medidor.jpg', base64_decode(
            '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='
        ));
    }

    private function lectura(User $socio): Lectura
    {
        return Lectura::factory()->for($socio, 'usuario')->create(['mes' => 10, 'gestion' => 2026]);
    }

    public function test_el_plomero_no_ve_finanzas_ni_multas(): void
    {
        foreach (['/finanzas', '/finanzas/exportar', '/multas', '/multas/exportar', '/mi-consumo', '/mis-pagos'] as $url) {
            $this->actingAs($this->plomero)->get($url)->assertForbidden();
        }

        $this->actingAs($this->plomero)->get('/lecturas/planilla')
            ->assertDontSee(route('finanzas.index'))
            ->assertDontSee(route('multas.index'))
            ->assertSee('Carga de Lecturas');

        // Los socios siguen viendo Finanzas y Multas
        $this->actingAs($this->ana)->get('/finanzas')->assertOk();
        $this->actingAs($this->ana)->get('/multas')->assertOk();
    }

    public function test_la_planilla_devuelve_las_lecturas_para_subir_las_fotos(): void
    {
        $this->actingAs($this->plomero)->postJson('/lecturas/planilla', [
            'mes' => 10, 'gestion' => 2026,
            'lecturas' => [$this->ana->id => ['actual' => '15', 'anterior' => '0']],
        ])->assertOk()->assertJson(['ids' => [$this->ana->id => Lectura::sole()->id]]);

        $this->actingAs($this->plomero)->get('/lecturas/planilla?mes=11&gestion=2026')
            ->assertSee('capture="environment"', false)
            ->assertSee('class="entrada-foto', false);
    }

    public function test_el_plomero_sube_la_foto_y_la_ven_hacienda_y_el_socio(): void
    {
        $lectura = $this->lectura($this->ana);

        $this->actingAs($this->plomero)->post("/lecturas/{$lectura->id}/foto", ['foto' => $this->foto()], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['mensaje' => 'Foto del medidor guardada ✓']);

        $lectura->refresh();
        $this->assertStringStartsWith('medidores/2026-10/lectura_'.$lectura->id.'_', $lectura->foto);
        $this->assertDatabaseHas('archivos', ['ruta' => $lectura->foto]);

        $this->actingAs($this->admin)->get("/lecturas/{$lectura->id}/foto")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($this->ana)->get("/lecturas/{$lectura->id}/foto")->assertOk();
        $this->actingAs($this->beto)->get("/lecturas/{$lectura->id}/foto")->assertForbidden();
        $this->actingAs($this->plomero)->get("/lecturas/{$lectura->id}/foto")->assertForbidden();

        $this->actingAs($this->admin)->get('/lecturas')->assertSee(route('lecturas.foto', $lectura));
        $this->actingAs($this->ana)->get('/mi-consumo')->assertSee('Ver foto del medidor');
    }

    public function test_el_plomero_no_reemplaza_una_foto_pero_hacienda_si(): void
    {
        $lectura = $this->lectura($this->ana);
        $this->actingAs($this->plomero)->post("/lecturas/{$lectura->id}/foto", ['foto' => $this->foto()], ['Accept' => 'application/json']);
        $primera = $lectura->fresh()->foto;

        $this->travel(2)->seconds();
        $this->actingAs($this->plomero)->post("/lecturas/{$lectura->id}/foto", ['foto' => $this->foto()], ['Accept' => 'application/json'])
            ->assertStatus(409);
        $this->assertSame($primera, $lectura->fresh()->foto);

        $this->actingAs($this->admin)->from("/lecturas/{$lectura->id}/editar")
            ->post("/lecturas/{$lectura->id}/foto", ['foto' => $this->foto()])
            ->assertRedirect("/lecturas/{$lectura->id}/editar")
            ->assertSessionHas('success');
        $this->assertNotSame($primera, $lectura->fresh()->foto);
        $this->assertFalse(Storage::disk('archivos')->exists($primera));
    }

    public function test_solo_acepta_fotos_y_el_socio_no_sube(): void
    {
        $lectura = $this->lectura($this->ana);

        $this->actingAs($this->plomero)->post("/lecturas/{$lectura->id}/foto",
            ['foto' => UploadedFile::fake()->createWithContent('virus.jpg', 'no soy una imagen')], ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->actingAs($this->ana)->post("/lecturas/{$lectura->id}/foto", ['foto' => $this->foto()])->assertForbidden();
        $this->assertNull($lectura->fresh()->foto);
    }

    public function test_al_borrar_la_lectura_se_borra_su_foto(): void
    {
        $lectura = $this->lectura($this->ana);
        $this->actingAs($this->plomero)->post("/lecturas/{$lectura->id}/foto", ['foto' => $this->foto()], ['Accept' => 'application/json']);
        $ruta = $lectura->fresh()->foto;

        $this->actingAs($this->admin)->delete("/lecturas/{$lectura->id}");

        $this->assertDatabaseMissing('archivos', ['ruta' => $ruta]);
    }
}
