<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Un socio solo puede tener una lectura por mes y gestión.
 * - Solo puede haber un balance por mes y gestión.
 * - `balance_mensuals.ingresos_multas`: lo cobrado por multas en ese mes. Lo calcula
 *   el sistema a partir de las multas pagadas; `ingresos` sigue siendo el monto manual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecturas', function (Blueprint $table) {
            $table->unique(['user_id', 'mes', 'gestion'], 'lecturas_socio_periodo_unique');
        });

        Schema::table('balance_mensuals', function (Blueprint $table) {
            $table->unique(['mes', 'gestion'], 'balance_mensuals_periodo_unique');
            $table->decimal('ingresos_multas', 10, 2)->default(0)->after('ingresos');
        });

        // Multas ya pagadas: se suman al balance del mes en que se cobraron
        $cobros = DB::table('multas')
            ->where('estado', 'pagado')
            ->whereNotNull('fecha_pago')
            ->get(['monto', 'fecha_pago'])
            ->groupBy(fn ($m) => date('Y-n', strtotime($m->fecha_pago)));

        foreach ($cobros as $periodo => $multas) {
            [$gestion, $mes] = array_map('intval', explode('-', $periodo));
            $total = $multas->sum('monto');

            $balance = DB::table('balance_mensuals')->where(compact('mes', 'gestion'))->first();

            if ($balance) {
                DB::table('balance_mensuals')->where('id', $balance->id)->update([
                    'ingresos_multas' => $total,
                    'saldo_final' => $balance->ingresos + $total - $balance->egresos,
                ]);
            } else {
                DB::table('balance_mensuals')->insert([
                    'mes' => $mes,
                    'gestion' => $gestion,
                    'ingresos' => 0,
                    'ingresos_multas' => $total,
                    'egresos' => 0,
                    'saldo_final' => $total,
                    'detalle' => 'Registro creado automáticamente por cobro de multas.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('balance_mensuals')->update(['saldo_final' => DB::raw('ingresos - egresos')]);

        Schema::table('balance_mensuals', function (Blueprint $table) {
            $table->dropUnique('balance_mensuals_periodo_unique');
            $table->dropColumn('ingresos_multas');
        });

        Schema::table('lecturas', function (Blueprint $table) {
            $table->dropUnique('lecturas_socio_periodo_unique');
        });
    }
};
