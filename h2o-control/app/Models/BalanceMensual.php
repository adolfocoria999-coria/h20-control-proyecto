<?php

namespace App\Models;

use App\Models\Concerns\RegistraEnHistorial;
use App\Support\Meses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BalanceMensual extends Model
{
    use HasFactory, RegistraEnHistorial;

    protected $table = 'balance_mensuals';

    // Calculados por el sistema: cambiarlos no es una acción del usuario
    protected array $ignorarEnHistorial = ['ingresos_multas', 'saldo_final'];

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
            'ingresos_multas' => 'decimal:2',
            'egresos' => 'decimal:2',
            'saldo_final' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // El saldo siempre se deriva de los ingresos (manuales + multas) y egresos
        static::saving(function (BalanceMensual $balance) {
            $balance->saldo_final = (float) $balance->ingresos + (float) $balance->ingresos_multas - (float) $balance->egresos;
        });
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
                'detalle' => 'Registro creado automáticamente por cobro de multas.',
            ]);
        }

        $balance->ingresos_multas = $total;
        $balance->save();
    }

    // Ingresos manuales + multas cobradas
    protected function ingresosTotales(): Attribute
    {
        return Attribute::get(fn () => (float) $this->ingresos + (float) $this->ingresos_multas);
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
            return 'Creó automáticamente '.$this->etiquetaHistorial().' por cobro de multas';
        }

        return Actividad::ACCIONES[$accion].' '.$this->etiquetaHistorial();
    }
}
