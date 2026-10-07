<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\Lectura;
use App\Models\Multa;
use App\Models\User;
use App\Services\PagosDelSocio;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MisPagosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $socio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        $this->admin = User::factory()->create(['rol_id' => RolUsuario::Admin->value, 'name' => 'Maria Hacienda']);
        $this->socio = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'name' => 'Ana Quispe']);
    }

    /** Hacienda confirma el pago de una lectura (10 m³ = Bs. 20) en la fecha indicada. */
    private function pagarLectura(int $mes, string $metodo, string $fecha, ?User $socio = null): Lectura
    {
        $this->travelTo($fecha);
        $lectura = Lectura::factory()->for($socio ?? $this->socio, 'usuario')
            ->create(['mes' => $mes, 'gestion' => 2026, 'lectura_anterior' => 0, 'lectura_actual' => 10]);
        $this->actingAs($this->admin)->patch("/lecturas/{$lectura->id}/pagar", ['metodo_pago' => $metodo]);

        return $lectura;
    }

    private function pagarMulta(string $metodo, string $fecha, int $monto = 30): Multa
    {
        $this->travelTo($fecha);
        $multa = Multa::factory()->for($this->socio, 'socio')->create(['monto' => $monto, 'tipo_multa' => 'Inasistencia a Asamblea']);
        $this->actingAs($this->admin)->post("/multas/{$multa->id}/pagar", ['metodo_pago' => $metodo]);

        return $multa;
    }

    private function verPagos(string $query = '')
    {
        return $this->actingAs($this->socio)->get('/mis-pagos'.$query)->assertOk();
    }

    public function test_el_pago_confirmado_aparece_en_el_historial_del_socio(): void
    {
        $this->pagarLectura(9, 'qr', '2026-10-15 09:45:00');

        $this->verPagos()
            ->assertSee('Agua – Septiembre 2026')
            ->assertSee('15/10/2026')
            ->assertSee('09:45')
            ->assertSee('📱 QR')
            ->assertSee('Bs. 20,00')
            ->assertSee('Maria Hacienda');
    }

    public function test_muestra_agua_y_multas_con_totales_por_metodo(): void
    {
        $this->pagarLectura(8, 'qr', '2026-09-10 10:00:00');
        $this->pagarLectura(9, 'efectivo', '2026-10-10 10:00:00');
        $this->pagarMulta('efectivo', '2026-10-12 10:00:00');

        $this->verPagos('?gestion=2026')
            ->assertViewHas('totales', ['total' => 70.0, 'qr' => 20.0, 'efectivo' => 50.0, 'cantidad' => 3])
            ->assertViewHas('pagos', fn ($p) => $p->total() === 3 && $p->first()->concepto === 'Multa – Inasistencia a Asamblea');
    }

    public function test_filtra_por_anio_mes_metodo_y_concepto(): void
    {
        $this->pagarLectura(8, 'qr', '2025-12-20 10:00:00');
        $this->pagarLectura(9, 'qr', '2026-10-05 10:00:00');
        $this->pagarLectura(10, 'efectivo', '2026-11-03 10:00:00');
        $this->pagarMulta('qr', '2026-10-20 10:00:00');

        $total = fn (string $q) => $this->verPagos($q)->viewData('pagos')->total();

        $this->assertSame(3, $total('?gestion=2026'));
        $this->assertSame(1, $total('?gestion=2025'));
        $this->assertSame(4, $total('?gestion=todas'));
        $this->assertSame(2, $total('?gestion=2026&mes=10'));
        $this->assertSame(1, $total('?gestion=2026&metodo=efectivo'));
        $this->assertSame(1, $total('?gestion=2026&tipo=multa'));
        $this->assertSame(1, $total('?gestion=2026&mes=10&tipo=agua&metodo=qr'));

        // Último instante del mes: entra en ese mes
        $this->pagarLectura(11, 'qr', '2026-10-31 23:59:00');
        $this->assertSame(3, $total('?gestion=2026&mes=10'));
    }

    public function test_por_defecto_muestra_el_anio_actual(): void
    {
        $this->pagarLectura(8, 'qr', '2025-06-10 10:00:00');
        $this->pagarLectura(9, 'qr', '2026-10-10 10:00:00');

        $this->travelTo('2026-10-15');
        $this->verPagos()->assertViewHas('pagos', fn ($p) => $p->total() === 1)
            ->assertViewHas('gestiones', [2026, 2025]);
    }

    public function test_el_socio_solo_ve_sus_propios_pagos_y_no_los_pendientes(): void
    {
        $otro = User::factory()->create(['rol_id' => RolUsuario::Socio->value]);
        $this->pagarLectura(9, 'qr', '2026-10-05 10:00:00', $otro);
        Lectura::factory()->for($this->socio, 'usuario')->create(['mes' => 10, 'gestion' => 2026]);

        $this->verPagos('?gestion=todas')->assertViewHas('pagos', fn ($p) => $p->total() === 0)
            ->assertSee('No tienes pagos registrados');
    }

    public function test_si_hacienda_anula_el_pago_desaparece_del_historial(): void
    {
        $multa = $this->pagarMulta('efectivo', '2026-10-05 10:00:00');
        $this->assertSame(1, $this->verPagos()->viewData('pagos')->total());

        $multa->fresh()->update(['estado' => Multa::PENDIENTE]);

        $this->assertSame(0, $this->verPagos()->viewData('pagos')->total());
    }

    public function test_pagina_el_historial(): void
    {
        $this->travelTo('2026-10-05 10:00:00');
        Multa::factory()->count(PagosDelSocio::POR_PAGINA + 3)->for($this->socio, 'socio')->create()
            ->each(fn (Multa $m) => $m->update(['estado' => Multa::PAGADO, 'metodo_pago' => 'qr']));

        $this->verPagos()->assertViewHas('pagos', fn ($p) => $p->total() === PagosDelSocio::POR_PAGINA + 3 && $p->count() === PagosDelSocio::POR_PAGINA);
        $this->verPagos('?page=2')->assertViewHas('pagos', fn ($p) => $p->count() === 3);
    }

    public function test_descargar_mis_pagos(): void
    {
        $this->pagarLectura(9, 'efectivo', '2026-10-15 09:45:00');

        $csv = $this->actingAs($this->socio)->get('/mis-pagos/exportar?gestion=2026')->assertOk()->streamedContent();

        $this->assertStringContainsString('"Fecha de pago";Concepto;Método;"Monto (Bs.)";"Registrado por"', $csv);
        $this->assertStringContainsString('"15/10/2026 09:45";"Agua – Septiembre 2026";Efectivo;20,00;"Maria Hacienda"', $csv);
    }

    public function test_el_menu_del_socio_tiene_mis_pagos(): void
    {
        $this->actingAs($this->socio)->get('/mi-consumo')
            ->assertSee(route('socio.pagos'))
            ->assertSee('Mis Pagos');
    }
}
