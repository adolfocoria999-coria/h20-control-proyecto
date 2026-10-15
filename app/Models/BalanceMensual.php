<?php

namespace App\Models;

use App\Models\Concerns\RegistraEnHistorial;
use App\Support\Meses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class BalanceMensual extends Model
{
    use HasFactory, RegistraEnHistorial;

    protected $table = 'balance_mensuals';

    // Calculados por el sistema: cambiarlos no es una acción del usuario
    protected array $ignorarEnHistorial = ['ingresos_agua', 'ingresos_multas', 'saldo_final'];

    // Desde qué mes el agua cobrada se suma sola (clave en `ajustes`, formato AAAA-MM).
    // Antes de esa fecha el agua se cargaba a mano dentro de `ingresos`.
    public const CLAVE_AGUA_DESDE = 'finanzas_agua_desde';

    public const AGUA_DESDE_POR_DEFECTO = '2026-10';

    private const DETALLE_AUTOMATICO = 'Registro creado automáticamente por cobros.';

    protected $fillable = [
        'mes',
        'gestion',
        'ingresos',
        'egresos',
        'saldo_final',
        'detalle',
        'comprobante_url',
    ];

    protected function casts(): array
    {
        return [
            'mes' => 'integer',
            'gestion' => 'integer',
            'ingresos' => 'decimal:2',
            'ingresos_agua' => 'decimal:2',
            'ingresos_multas' => 'decimal:2',
            'egresos' => 'decimal:2',
            'saldo_final' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // El saldo siempre se deriva de los ingresos (agua + multas + otros) y egresos
        static::saving(function (BalanceMensual $balance) {
            $balance->saldo_final = $balance->ingresos_totales - (float) $balance->egresos;
        });
    }

    // Primer día del mes desde el que el agua se suma automáticamente
    public static function aguaDesde(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', Ajuste::valor(self::CLAVE_AGUA_DESDE, self::AGUA_DESDE_POR_DEFECTO).'-01')->startOfDay();
    }

    public static function aguaAutomatica(int $mes, int $gestion): bool
    {
        return Carbon::create($gestion, $mes, 1)->gte(self::aguaDesde());
    }

    /**
     * Recalcula el agua cobrada en un mes a partir de las lecturas pagadas ese mes
     * (según la fecha de pago, igual que el arqueo). Antes del mes de inicio no hace nada.
     */
    public static function recalcularAgua(int $mes, int $gestion): void
    {
        if (! self::aguaAutomatica($mes, $gestion)) {
            return;
        }

        $desde = Carbon::create($gestion, $mes, 1)->startOfMonth();
        $total = (float) Lectura::where('estado', Lectura::PAGADO)
            ->whereBetween('fecha_pago', [$desde, $desde->copy()->endOfMonth()])
            ->sum('monto_pagado');

        self::fijarCobrado('ingresos_agua', $total, $mes, $gestion);
    }

    /**
     * Recalcula lo cobrado por multas en un mes a partir de las multas pagadas
     * (no se edita a mano). Si el mes no tiene balance, lo crea.
     */
    public static function recalcularMultas(int $mes, int $gestion): void
    {
        $total = (float) Multa::where('estado', Multa::PAGADO)
            ->whereYear('fecha_pago', $gestion)
            ->whereMonth('fecha_pago', $mes)
            ->sum('monto');

        self::fijarCobrado('ingresos_multas', $total, $mes, $gestion);
    }

    // Guarda lo cobrado (agua o multas) en el balance del mes; si no existe, lo crea
    private static function fijarCobrado(string $columna, float $total, int $mes, int $gestion): void
    {
        $balance = static::firstWhere(['mes' => $mes, 'gestion' => $gestion]);

        if (! $balance) {
            if ($total == 0) {
                return;
            }
            $balance = new static([
                'mes' => $mes,
                'gestion' => $gestion,
                'ingresos' => 0,
                'egresos' => 0,
                'detalle' => self::DETALLE_AUTOMATICO,
            ]);
        }

        $balance->{$columna} = $total;
        $balance->save();
    }

    // Agua cobrada + multas cobradas + otros ingresos (manuales)
    protected function ingresosTotales(): Attribute
    {
        return Attribute::get(fn () => (float) $this->ingresos_agua + (float) $this->ingresos_multas + (float) $this->ingresos);
    }

    // En este mes el agua se suma sola (desde el mes de inicio)
    protected function conAguaAutomatica(): Attribute
    {
        return Attribute::get(fn () => self::aguaAutomatica($this->mes, $this->gestion));
    }

    protected function mesNombre(): Attribute
    {
        return Attribute::get(fn () => Meses::nombre($this->mes));
    }

    public function scopeCronologico(Builder $query): Builder
    {
        return $query->orderBy('gestion')->orderBy('mes')->orderBy('id');
    }

    // ---------- Historial ----------

    protected function moduloHistorial(): string
    {
        return 'finanzas';
    }

    protected function etiquetaHistorial(): string
    {
        return "el balance de {$this->mes_nombre} {$this->gestion}";
    }

    protected function descripcionHistorial(string $accion): string
    {
        if ($accion === 'creado' && str_starts_with((string) $this->detalle, 'Registro creado automáticamente')) {
            return 'Creó automáticamente '.$this->etiquetaHistorial().' por cobros de agua o multas';
        }

        return Actividad::ACCIONES[$accion].' '.$this->etiquetaHistorial();
    }
}
