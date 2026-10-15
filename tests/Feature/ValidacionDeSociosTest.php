<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\User;
use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidacionDeSociosTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolSeeder::class);
        $this->superadmin = User::factory()->create(['rol_id' => RolUsuario::SuperAdmin->value]);
    }

    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'name' => 'Juan Pérez Mamani',
            'ci' => '1234567',
            'telefono' => '71234567',
            'email' => 'juan@otb.com',
            'password' => 'secreto123',
            'rol_id' => RolUsuario::Socio->value,
        ];
    }

    public function test_rechaza_datos_que_no_corresponden_a_cada_casilla(): void
    {
        // El caso de la captura: números en el nombre, letras en el C.I. y en el teléfono
        $this->actingAs($this->superadmin)->post('/usuarios', $this->datos([
            'name' => '452224', 'ci' => 'hjnjnj', 'telefono' => 'bh k bh',
        ]))->assertSessionHasErrors(['name', 'ci', 'telefono']);

        $this->assertDatabaseMissing('users', ['email' => 'juan@otb.com']);
    }

    public function test_formatos_invalidos(): void
    {
        $invalidos = [
            'name' => ['Juan123', 'Ana@', 'ab'],
            'ci' => ['123', '12345678901', '1234567-', '1234567 XX', '12.345.678'],
            'telefono' => ['12345678', '7123456', '712345678', '2234567'],
            'email' => ['juan', 'juan@'],
            'password' => ['corta'],
        ];

        foreach ($invalidos as $campo => $valores) {
            foreach ($valores as $valor) {
                $this->actingAs($this->superadmin)->post('/usuarios', $this->datos([$campo => $valor]))
                    ->assertSessionHasErrors($campo, "«{$valor}» debería ser inválido para {$campo}");
            }
        }
    }

    public function test_acepta_y_ordena_datos_validos(): void
    {
        $this->actingAs($this->superadmin)->post('/usuarios', $this->datos([
            'name' => '  María   José Ñuflo-Quispe ',
            'ci' => '1234567-1a lp',
            'telefono' => '+591 712-34567',
            'email' => ' Maria@OTB.com ',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('usuarios.index'));

        $this->assertDatabaseHas('users', [
            'name' => 'María José Ñuflo-Quispe',
            'ci' => '1234567-1A LP',
            'telefono' => '71234567',
            'email' => 'maria@otb.com',
        ]);
    }

    public function test_el_ci_no_se_puede_repetir_pero_el_socio_puede_conservar_el_suyo(): void
    {
        $existente = User::factory()->create(['rol_id' => RolUsuario::Socio->value, 'ci' => '7654321']);

        $this->actingAs($this->superadmin)->post('/usuarios', $this->datos(['ci' => '7654321']))
            ->assertSessionHasErrors(['ci' => 'Ya hay un socio registrado con ese carnet de identidad.']);

        $this->actingAs($this->superadmin)->put("/usuarios/{$existente->id}", $this->datos([
            'ci' => '7654321', 'email' => $existente->email, 'password' => '',
        ]))->assertSessionHasNoErrors();
    }

    public function test_ci_y_telefono_son_opcionales(): void
    {
        $this->actingAs($this->superadmin)->post('/usuarios', $this->datos(['ci' => '', 'telefono' => '']))
            ->assertSessionHasNoErrors();
    }

    public function test_el_formulario_conserva_lo_escrito_y_muestra_el_error_en_su_casilla(): void
    {
        $this->actingAs($this->superadmin)->from('/usuarios/crear')
            ->post('/usuarios', $this->datos(['telefono' => '123']))
            ->assertRedirect('/usuarios/crear');

        $this->actingAs($this->superadmin)->get('/usuarios/crear')
            ->assertSee('value="Juan Pérez Mamani"', false)
            ->assertSee('El teléfono debe ser un celular de 8 dígitos');
    }
}
