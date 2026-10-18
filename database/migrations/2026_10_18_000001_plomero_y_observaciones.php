<?php

use App\Enums\RolUsuario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - El rol "Técnico" pasa a llamarse "Plomero", como le dicen en la OTB
 *   (también en el historial ya registrado).
 * - Observación de cada lectura: lo que anota el plomero en el recorrido
 *   (medidor dañado, posible fuga, etc.). Es interna: la ve Hacienda.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('rols')->updateOrInsert(
            ['id' => RolUsuario::Plomero->value],
            ['nombre' => 'Plomero', 'descripcion' => 'Recorre los medidores y anota las lecturas y observaciones desde su celular.']
        );
        DB::table('actividades')->where('rol', 'tecnico')->update(['rol' => 'plomero']);

        Schema::table('lecturas', function (Blueprint $table) {
            $table->string('observacion', 255)->nullable()->after('consumo');
        });
    }

    public function down(): void
    {
        Schema::table('lecturas', function (Blueprint $table) {
            $table->dropColumn('observacion');
        });

        DB::table('actividades')->where('rol', 'plomero')->update(['rol' => 'tecnico']);
        DB::table('rols')->where('id', RolUsuario::Plomero->value)
            ->update(['nombre' => 'Técnico', 'descripcion' => 'Recorre los medidores y anota las lecturas desde su celular.']);
    }
};
