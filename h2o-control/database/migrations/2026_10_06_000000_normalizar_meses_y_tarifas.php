<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1. Completa las columnas de `tarifas_multas` en bases de datos creadas
 *    cuando su migración original estaba vacía.
 * 2. Convierte `mes` de texto ("Enero") a número (1-12) en `lecturas`
 *    y `balance_mensuals`, para poder ordenar y filtrar en SQL.
 */
return new class extends Migration
{
    // Copia local: una migración no debe depender de código de la app que puede cambiar
    private const MESES = [
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
        'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
        'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
    ];

    private const TABLAS_CON_MES = ['lecturas', 'balance_mensuals'];

    public function up(): void
    {
        Schema::table('tarifas_multas', function (Blueprint $table) {
            if (! Schema::hasColumn('tarifas_multas', 'nombre')) {
                $table->string('nombre')->default('');
            }
            if (! Schema::hasColumn('tarifas_multas', 'monto_predeterminado')) {
                $table->decimal('monto_predeterminado', 10, 2)->default(0);
            }
            if (! Schema::hasColumn('tarifas_multas', 'descripcion')) {
                $table->text('descripcion')->nullable();
            }
        });

        foreach (self::TABLAS_CON_MES as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->unsignedTinyInteger('mes_num')->nullable()->after('mes');
            });

            DB::table($tabla)->orderBy('id')->each(function ($fila) use ($tabla) {
                DB::table($tabla)->where('id', $fila->id)->update([
                    'mes_num' => $this->aNumero($fila->mes, $fila->created_at),
                ]);
            });

            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn('mes');
            });

            Schema::table($tabla, function (Blueprint $table) {
                $table->renameColumn('mes_num', 'mes');
            });

            Schema::table($tabla, function (Blueprint $table) {
                $table->unsignedTinyInteger('mes')->nullable(false)->change();
                $table->index(['gestion', 'mes']);
            });
        }
    }

    public function down(): void
    {
        $nombres = array_flip(array_diff_key(self::MESES, ['setiembre' => 0]));

        foreach (self::TABLAS_CON_MES as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropIndex(['gestion', 'mes']);
                $table->string('mes_texto')->nullable()->after('mes');
            });

            DB::table($tabla)->orderBy('id')->each(function ($fila) use ($tabla, $nombres) {
                DB::table($tabla)->where('id', $fila->id)->update([
                    'mes_texto' => ucfirst($nombres[$fila->mes] ?? ''),
                ]);
            });

            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn('mes');
            });

            Schema::table($tabla, function (Blueprint $table) {
                $table->renameColumn('mes_texto', 'mes');
            });
        }
        // Las columnas de tarifas_multas se conservan: la migración original ya las define.
    }

    private function aNumero(mixed $mes, ?string $creadoEn): int
    {
        if (is_numeric($mes) && (int) $mes >= 1 && (int) $mes <= 12) {
            return (int) $mes;
        }

        $texto = mb_strtolower(trim((string) $mes));
        if (isset(self::MESES[$texto])) {
            return self::MESES[$texto];
        }

        // Valor irreconocible: se usa el mes en que se creó el registro
        return $creadoEn ? (int) date('n', strtotime($creadoEn)) : 1;
    }
};
