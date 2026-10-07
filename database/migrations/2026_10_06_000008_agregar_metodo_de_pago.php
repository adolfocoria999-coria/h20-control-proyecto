<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Datos del cobro para el arqueo mensual:
 * - metodo_pago: qr | efectivo
 * - cobrado_por: usuario que registró el pago
 * - lecturas.fecha_pago: cuándo se cobró (el arqueo agrupa por esta fecha, no por el mes de la lectura)
 * - lecturas.monto_pagado: monto cobrado en ese momento (no cambia si luego cambia la tarifa)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecturas', function (Blueprint $table) {
            $table->string('metodo_pago', 10)->nullable()->after('estado');
            $table->timestamp('fecha_pago')->nullable()->after('metodo_pago');
            $table->decimal('monto_pagado', 10, 2)->nullable()->after('fecha_pago');
            $table->foreignId('cobrado_por')->nullable()->after('monto_pagado')->constrained('users')->nullOnDelete();
            $table->index(['estado', 'fecha_pago']);
        });

        Schema::table('multas', function (Blueprint $table) {
            $table->string('metodo_pago', 10)->nullable()->after('estado');
            $table->foreignId('cobrado_por')->nullable()->after('fecha_pago')->constrained('users')->nullOnDelete();
            $table->index(['estado', 'fecha_pago']);
        });

        // Lecturas ya pagadas: la mejor aproximación de la fecha de cobro es su última modificación,
        // y el monto, el consumo por la tarifa vigente. El método queda sin especificar.
        $tarifa = (float) (DB::table('ajustes')->where('clave', 'tarifa_m3')->value('valor') ?? 2);

        DB::table('lecturas')->where('estado', 'pagado')->orderBy('id')->each(function ($lectura) use ($tarifa) {
            DB::table('lecturas')->where('id', $lectura->id)->update([
                'fecha_pago' => $lectura->updated_at ?? $lectura->created_at,
                'monto_pagado' => round((float) $lectura->consumo * $tarifa, 2),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('multas', function (Blueprint $table) {
            $table->dropIndex(['estado', 'fecha_pago']);
            $table->dropConstrainedForeignId('cobrado_por');
            $table->dropColumn('metodo_pago');
        });

        Schema::table('lecturas', function (Blueprint $table) {
            $table->dropIndex(['estado', 'fecha_pago']);
            $table->dropConstrainedForeignId('cobrado_por');
            $table->dropColumn(['metodo_pago', 'fecha_pago', 'monto_pagado']);
        });
    }
};
