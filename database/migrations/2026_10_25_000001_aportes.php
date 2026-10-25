<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aportes (cuotas extraordinarias): aniversario, mantenimiento, compra de material...
 *
 * - `aportes`: la actividad, el monto por socio y la fecha límite.
 * - `multas.aporte_id`: cada socio recibe su cuota como un cobro pendiente que se
 *   paga igual que una multa (QR o efectivo, con recibo), pero marcado como aporte.
 * - `balance_mensuals.ingresos_aportes`: lo cobrado por aportes en el mes, aparte
 *   de las multas (lo calcula el sistema).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aportes', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 120);
            $table->text('descripcion')->nullable();
            $table->decimal('monto', 10, 2);
            $table->date('fecha_limite')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('comunicado_id')->nullable()->constrained('comunicados')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('multas', function (Blueprint $table) {
            $table->foreignId('aporte_id')->nullable()->after('tarifa_multa_id')->constrained('aportes')->cascadeOnDelete();
        });

        Schema::table('balance_mensuals', function (Blueprint $table) {
            $table->decimal('ingresos_aportes', 10, 2)->default(0)->after('ingresos_multas');
        });
    }

    public function down(): void
    {
        Schema::table('balance_mensuals', function (Blueprint $table) {
            $table->dropColumn('ingresos_aportes');
        });
        Schema::table('multas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aporte_id');
        });
        Schema::dropIfExists('aportes');
    }
};
