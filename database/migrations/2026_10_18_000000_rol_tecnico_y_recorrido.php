<?php

use App\Enums\RolUsuario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Rol Técnico: recorre los medidores y anota las lecturas desde el celular
 *   (solo accede a la planilla de carga).
 * - Recorrido: zona/calle y número de orden de cada socio, para que la planilla
 *   aparezca en el orden en que el técnico visita las casas.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('rols')->updateOrInsert(
            ['id' => RolUsuario::Plomero->value],
            ['nombre' => 'Técnico', 'descripcion' => 'Recorre los medidores y anota las lecturas desde su celular.']
        );

        Schema::table('users', function (Blueprint $table) {
            $table->string('zona', 60)->nullable()->after('telefono');
            $table->unsignedSmallInteger('orden_recorrido')->nullable()->after('zona');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['zona', 'orden_recorrido']);
        });

        DB::table('users')->where('rol_id', RolUsuario::Plomero->value)->update(['rol_id' => RolUsuario::Socio->value]);
        DB::table('rols')->where('id', RolUsuario::Plomero->value)->delete();
    }
};
