<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Ajuste;
use App\Models\User;
use App\Services\QrPago;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QrPagoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $socio;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(RolSeeder::class);

        $this->admin = User::factory()->create(['rol_id' => RolUsuario::Admin->value]);
        $this->socio = User::factory()->create(['rol_id' => RolUsuario::Socio->value]);
    }

    // PNG real de 1x1 px (UploadedFile::fake()->image() requiere la extensión GD)
    private function imagenPng(string $nombre): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        ));
    }

    private function qrQueVenceEn(int $dias): void
    {
        Storage::disk('public')->put('qr/actual.png', 'imagen');
        Ajuste::guardar(QrPago::CLAVE_RUTA, 'qr/actual.png');
        Ajuste::guardar(QrPago::CLAVE_VENCE, today()->addDays($dias)->toDateString());
    }

    public function test_hacienda_sube_el_qr_con_fecha_de_vencimiento(): void
    {
        $vence = today()->addMonths(3)->toDateString();

        $this->actingAs($this->admin)->post('/finanzas/qr', [
            'qr_imagen' => $this->imagenPng('qr.png'),
            'qr_vence_el' => $vence,
        ])->assertRedirect(route('finanzas.index').'#qr');

        $qr = QrPago::actual();
        Storage::disk('public')->assertExists($qr->ruta);
        $this->assertSame($vence, $qr->venceEl->toDateString());
        $this->assertSame(QrPago::VIGENTE, $qr->estado());
    }

    public function test_la_primera_vez_la_imagen_es_obligatoria_y_la_fecha_no_puede_ser_pasada(): void
    {
        $this->actingAs($this->admin)->post('/finanzas/qr', [
            'qr_vence_el' => today()->subDay()->toDateString(),
        ])->assertSessionHasErrorsIn('qr', ['qr_imagen', 'qr_vence_el']);
    }

    public function test_se_puede_cambiar_solo_la_fecha_o_reemplazar_la_imagen(): void
    {
        $this->qrQueVenceEn(2);

        // Solo la fecha
        $this->actingAs($this->admin)->post('/finanzas/qr', [
            'qr_vence_el' => today()->addYear()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame('qr/actual.png', QrPago::actual()->ruta);

        // Imagen nueva: la anterior se borra
        $this->actingAs($this->admin)->post('/finanzas/qr', [
            'qr_imagen' => $this->imagenPng('nuevo.png'),
            'qr_vence_el' => today()->addYear()->toDateString(),
        ]);
        Storage::disk('public')->assertMissing('qr/actual.png');
        Storage::disk('public')->assertExists(QrPago::actual()->ruta);
    }

    public function test_el_socio_no_puede_cambiar_el_qr(): void
    {
        $this->actingAs($this->socio)->post('/finanzas/qr', [
            'qr_imagen' => $this->imagenPng('qr.png'),
            'qr_vence_el' => today()->addMonth()->toDateString(),
        ])->assertForbidden();
    }

    public function test_estados_segun_la_fecha(): void
    {
        $this->assertSame(QrPago::SIN_QR, QrPago::actual()->estado());

        $this->qrQueVenceEn(30);
        $this->assertSame(QrPago::VIGENTE, QrPago::actual()->estado());

        $this->qrQueVenceEn(QrPago::DIAS_AVISO);
        $this->assertSame(QrPago::POR_VENCER, QrPago::actual()->estado());

        $this->qrQueVenceEn(0);
        $this->assertSame(QrPago::POR_VENCER, QrPago::actual()->estado());
        $this->assertSame('El QR de pago vence hoy.', QrPago::actual()->mensaje());

        $this->qrQueVenceEn(-1);
        $this->assertSame(QrPago::VENCIDO, QrPago::actual()->estado());
    }

    public function test_alerta_para_administradores_cuando_esta_por_vencer(): void
    {
        $this->qrQueVenceEn(3);

        $this->actingAs($this->admin)->get('/lecturas')->assertSee('El QR de pago vence en 3 días');
        $this->actingAs($this->socio)->get('/mi-consumo')->assertDontSee('El QR de pago vence en');
    }

    public function test_sin_alerta_cuando_esta_vigente(): void
    {
        $this->qrQueVenceEn(60);

        $this->actingAs($this->admin)->get('/lecturas')->assertDontSee('Actualizar QR');
    }

    public function test_el_socio_ve_el_qr_vigente_pero_no_uno_vencido(): void
    {
        $this->qrQueVenceEn(10);
        $this->actingAs($this->socio)->get('/mi-consumo')
            ->assertSee('storage/qr/actual.png')
            ->assertDontSee('QR de pago no disponible');

        $this->qrQueVenceEn(-1);
        $this->actingAs($this->socio)->get('/mi-consumo')
            ->assertDontSee('storage/qr/actual.png')
            ->assertSee('QR de pago no disponible');
    }
}
