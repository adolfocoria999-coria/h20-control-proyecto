<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Se dejó de registrar el inicio/cierre de sesión, los intentos fallidos sueltos
 * y los borrados del propio historial. Se eliminan los que se alcanzaron a guardar.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('actividades')->whereIn('accion', ['inicio_sesion', 'cierre_sesion', 'acceso_fallido'])->delete();
        DB::table('actividades')->where('modulo', 'historial')->where('accion', 'eliminado')->delete();
    }

    public function down(): void
    {
        // Los registros eliminados no se pueden recuperar
    }
};
