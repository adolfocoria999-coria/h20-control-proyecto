<?php

use App\Models\Ajuste;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada lectura guarda la tarifa por m³ con la que se calculó. Así, si la tarifa
 * cambia, las lecturas ya registradas (pagadas o pendientes) conservan su precio.
 * Las existentes se completan con la tarifa vigente (hasta hoy nunca cambió).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecturas', function (Blueprint $table) {
            $table->decimal('tarifa_m3', 8, 2)->nullable()->after('consumo');
        });

        $tarifa = DB::table('ajustes')->where('clave', Ajuste::TARIFA_M3)->value('valor') ?? Ajuste::TARIFA_M3_POR_DEFECTO;
        DB::table('lecturas')->whereNull('tarifa_m3')->update(['tarifa_m3' => $tarifa]);
    }

    public function down(): void
    {
        Schema::table('lecturas', function (Blueprint $table) {
            $table->dropColumn('tarifa_m3');
        });
    }
};
