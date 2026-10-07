<?php

namespace App\Models;

use App\Enums\MetodoPago;
use App\Models\Concerns\RegistraEnHistorial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Multa extends Model
{
    use HasFactory, RegistraEnHistorial;

    protected $fillable = [
        'user_id',
        'tarifa_multa_id',
        'tipo_multa',
        'monto',
        'motivo',
        'fecha_multa',
        'estado',
        'metodo_pago',
        'fecha_pago',
        'comprobante_pago',
    ];

    protected array $ignorarEnHistorial = ['cobrado_por'];

    public const PENDIENTE = 'pendiente';

    public const PAGADO = 'pagado';

    public const CONDONADO = 'condonado';

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_multa' => 'date',
            'fecha_pago' => 'date',
            'metodo_pago' => MetodoPago::class,
        ];
    }

    protected static function booted(): void
    {
        // La fecha de pago acompaña siempre al estado
        static::saving(function (Multa $multa) {
            if ($multa->estado === self::PAGADO) {
                $multa->fecha_pago ??= today();
                $multa->cobrado_por ??= auth()->id();
            } else {
                $multa->fecha_pago = null;
                $multa->metodo_pago = null;
                $multa->cobrado_por = null;
            }
        });

        // Cualquier cambio en una multa cobrada actualiza Finanzas
        static::saved(fn (Multa $multa) => $multa->actualizarFinanzas());
        static::deleted(fn (Multa $multa) => $multa->actualizarFinanzas());
    }

    /**
     * Recalcula los meses afectados: donde estaba cobrada antes y donde está ahora.
     */
    private function actualizarFinanzas(): void
    {
        collect([$this->getOriginal('fecha_pago'), $this->fecha_pago])
            ->filter()
            ->map(fn ($fecha) => [$fecha->month, $fecha->year])
            ->unique(fn ($periodo) => implode('-', $periodo))
            ->each(fn ($periodo) => BalanceMensual::recalcularMultas(...$periodo));
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', self::PENDIENTE);
    }

    public function socio()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    // Quién registró el cobro
    public function cobrador()
    {
        return $this->belongsTo(User::class, 'cobrado_por')->withTrashed();
    }

    public function tarifa()
    {
        return $this->belongsTo(TarifaMulta::class, 'tarifa_multa_id');
    }

    // ---------- Historial ----------

    protected function moduloHistorial(): string
    {
        return 'multas';
    }

    protected function etiquetaHistorial(): string
    {
        return "la multa «{$this->tipo_multa}» de Bs. ".number_format((float) $this->monto, 2, ',', '.')
            .' de '.($this->socio->name ?? 'un socio');
    }

    protected function accionHistorial(string $evento, array $cambios): string
    {
        return match ($cambios['estado']['despues'] ?? null) {
            self::PAGADO => 'pago',
            self::CONDONADO => 'condonacion',
            default => $evento,
        };
    }

    protected function descripcionHistorial(string $accion): string
    {
        if ($accion === 'pago') {
            $via = match ($this->metodo_pago) {
                MetodoPago::Qr => ' por QR',
                MetodoPago::Efectivo => ' en efectivo',
                default => '',
            };

            return "Registró el pago{$via} de ".$this->etiquetaHistorial();
        }

        return Actividad::ACCIONES[$accion].' '.$this->etiquetaHistorial();
    }
}
