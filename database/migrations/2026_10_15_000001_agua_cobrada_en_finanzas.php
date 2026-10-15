<?php

use App\Models\BalanceMensual;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El agua cobrada pasa a Finanzas sola, igual que las multas:
 * - `balance_mensuals.ingresos_agua`: lo cobrado por lecturas en el mes (según la
 *   fecha de pago). Lo calcula el sistema.
 * - Solo desde octubre de 2026 (ajuste `finanzas_agua_desde`): en los meses
 *   anteriores Hacienda ya cargó el agua a mano en `ingresos`, que pasa a
 *   llamarse "Otros ingresos". Así no se cuenta dos veces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('balance_mensuals', function (Blueprint $table) {
            $table->decimal('ingresos_agua', 10, 2)->default(0)->after('ingresos');
        });

        DB::table('ajustes')->updateOrInsert(
            ['clave' => BalanceMensual::CLAVE_AGUA_DESDE],
            ['valor' => BalanceMensual::AGUA_DESDE_POR_DEFECTO, 'created_at' => now(), 'updated_at' => now()]
        );

        // Lecturas ya cobradas desde el mes de inicio
        DB::table('lecturas')
            ->where('estado', 'pagado')
            ->where('fecha_pago', '>=', BalanceMensual::aguaDesde())
            ->pluck('fecha_pago')
            ->map(fn ($fecha) => [(int) date('n', strtotime($fecha)), (int) date('Y', strtotime($fecha))])
            ->unique(fn ($periodo) => implode('-', $periodo))
            ->each(fn ($periodo) => BalanceMensual::recalcularAgua(...$periodo));
    }

    public function down(): void
    {
        Schema::table('balance_mensuals', function (Blueprint $table) {
            $table->dropColumn('ingresos_agua');
        });

        DB::table('ajustes')->where('clave', BalanceMensual::CLAVE_AGUA_DESDE)->delete();

        // Saldo sin el agua automática
        DB::table('balance_mensuals')->update(['saldo_final' => DB::raw('ingresos + ingresos_multas - egresos')]);
    }
};
