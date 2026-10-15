<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Lectura;
use App\Models\Multa;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DocumentosPdfTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $socio;

    private User $otroSocio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        $this->admin = User::factory()->create(['rol_id' => RolUsuario::Admin->value, 'name' => 'María Hacienda']);
        $this->socio = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Ana Quispe', 'ci' => '1234567']);
        $this->otroSocio = User::factory()->create(['rol_id' => RolUsuario::Socio->value]);
    }

    private function assertPdf(TestResponse $respuesta, string $nombre): void
    {
        $respuesta->assertOk()->assertDownload($nombre);
        $this->assertSame('application/pdf', $respuesta->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $respuesta->getContent());
    }

    private function lecturaPagada(?User $socio = null): Lectura
    {
        $lectura = Lectura::factory()->for($socio ?? $this->socio, 'usuario')
            ->create(['mes' => 9, 'gestion' => 2026, 'lectura_anterior' => 100, 'lectura_actual' => 112]);
        $this->actingAs($this->admin)->patch("/lecturas/{$lectura->id}/pagar", ['metodo_pago' => 'efectivo']);

        return $lectura->fresh();
    }

    private function multaPagada(?User $socio = null): Multa
    {
        $multa = Multa::factory()->for($socio ?? $this->socio, 'socio')->create(['motivo' => 'No asistió a la asamblea']);
        $this->actingAs($this->admin)->post("/multas/{$multa->id}/pagar", ['metodo_pago' => 'qr']);

        return $multa->fresh();
    }

    // ---------- Recibos ----------

    public function test_hacienda_descarga_el_recibo_de_agua_y_de_multa(): void
    {
        $lectura = $this->lecturaPagada();
        $multa = $this->multaPagada();
        $this->assertSame(Multa::PAGADO, $multa->estado);

        $this->assertPdf($this->actingAs($this->admin)->get("/recibos/agua/{$lectura->id}"), 'recibo_AG-'.str_pad($lectura->id, 6, '0', STR_PAD_LEFT).'.pdf');
        $this->assertPdf($this->actingAs($this->admin)->get("/recibos/multa/{$multa->id}"), 'recibo_MU-'.str_pad($multa->id, 6, '0', STR_PAD_LEFT).'.pdf');
    }

    public function test_el_socio_descarga_sus_recibos_pero_no_los_de_otros(): void
    {
        $suya = $this->lecturaPagada();
        $ajena = $this->lecturaPagada($this->otroSocio);
        $multaAjena = $this->multaPagada($this->otroSocio);

        $this->assertPdf($this->actingAs($this->socio)->get("/recibos/agua/{$suya->id}"), 'recibo_AG-'.str_pad($suya->id, 6, '0', STR_PAD_LEFT).'.pdf');
        $this->actingAs($this->socio)->get("/recibos/agua/{$ajena->id}")->assertForbidden();
        $this->actingAs($this->socio)->get("/recibos/multa/{$multaAjena->id}")->assertForbidden();
    }

    public function test_no_hay_recibo_de_un_cobro_pendiente_ni_sin_sesion(): void
    {
        $pendiente = Lectura::factory()->for($this->socio, 'usuario')->create();
        $multa = Multa::factory()->for($this->socio, 'socio')->create();

        $this->actingAs($this->admin)->get("/recibos/agua/{$pendiente->id}")->assertNotFound();
        $this->actingAs($this->admin)->get("/recibos/multa/{$multa->id}")->assertNotFound();

        auth()->logout();
        $this->get("/recibos/agua/{$pendiente->id}")->assertRedirect(route('login'));
    }

    public function test_los_botones_de_recibo_aparecen_solo_en_cobros_pagados(): void
    {
        $pagada = $this->lecturaPagada();
        $pendiente = Lectura::factory()->for($this->socio, 'usuario')->create(['mes' => 10, 'gestion' => 2026]);
        $multa = $this->multaPagada();

        $this->actingAs($this->admin)->get('/lecturas')
            ->assertSee(route('recibos.agua', $pagada))
            ->assertDontSee(route('recibos.agua', $pendiente));
        $this->actingAs($this->socio)->get('/multas')->assertSee(route('recibos.multa', $multa));
        $this->actingAs($this->socio)->get('/mis-pagos?gestion=todas')
            ->assertSee(route('recibos.agua', $pagada))
            ->assertSee(route('recibos.multa', $multa))
            ->assertSee(route('socio.pagos.pdf', ['gestion' => 'todas']), false);
    }

    // ---------- Historial de pagos y arqueo ----------

    public function test_el_socio_descarga_su_historial_de_pagos_en_pdf(): void
    {
        $this->lecturaPagada();

        $this->assertPdf($this->actingAs($this->socio)->get('/mis-pagos/pdf?gestion=todas'), 'mis_pagos_'.date('Y-m-d').'.pdf');
        $this->assertDatabaseHas('actividades', ['descripcion' => 'Descargó su historial de pagos en PDF']);
    }

    public function test_hacienda_descarga_el_acta_de_arqueo_y_el_socio_no(): void
    {
        $this->lecturaPagada();
        $mes = (int) date('n');
        $anio = (int) date('Y');

        $this->assertPdf(
            $this->actingAs($this->admin)->get("/finanzas/arqueo/pdf?gestion={$anio}&mes={$mes}"),
            'arqueo_'.$anio.'_'.str_pad((string) $mes, 2, '0', STR_PAD_LEFT).'.pdf'
        );
        $this->actingAs($this->admin)->get("/finanzas/arqueo?gestion={$anio}&mes={$mes}")->assertSee('Acta en PDF');

        $this->actingAs($this->socio)->get('/finanzas/arqueo/pdf')->assertForbidden();
    }

    public function test_el_arqueo_de_un_mes_sin_cobros_tambien_se_genera(): void
    {
        $this->assertPdf($this->actingAs($this->admin)->get('/finanzas/arqueo/pdf?gestion=2020&mes=1'), 'arqueo_2020_01.pdf');
    }
}
