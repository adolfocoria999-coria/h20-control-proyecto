<?php

namespace App\Models;

use App\Enums\MetodoPago;
use App\Models\Concerns\RegistraEnHistorial;
use App\Support\Meses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lectura extends Model
{
    use HasFactory, RegistraEnHistorial;

    public const PENDIENTE = 'pendiente';

    public const PAGADO = 'pagado';

    // Permitimos que estos campos reciban datos
    protected $fillable = [
        'user_id',
        'mes',
        'gestion',
        'lectura_anterior',
        'lectura_actual',
        'consumo',
        'observacion',
        'estado',
        'metodo_pago',
    ];

    // Datos internos del cobro: el historial ya registra la acción de pago
    protected array $ignorarEnHistorial = ['fecha_pago', 'monto_pagado', 'cobrado_por', 'tarifa_m3'];

    protected function casts(): array
    {
        return [
            'mes' => 'integer',
            'gestion' => 'integer',
            'lectura_anterior' => 'decimal:2',
            'lectura_actual' => 'decimal:2',
            'consumo' => 'decimal:2',
            'tarifa_m3' => 'decimal:2',
            'metodo_pago' => MetodoPago::class,
            'fecha_pago' => 'datetime',
            'monto_pagado' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // El consumo siempre se deriva de las lecturas
        static::saving(function (Lectura $lectura) {
            $lectura->consumo = (float) $lectura->lectura_actual - (float) $lectura->lectura_anterior;

            // La tarifa se fija al registrar la lectura: si luego cambia, esta conserva su precio
            $lectura->tarifa_m3 ??= Ajuste::tarifaM3();

            // Datos del cobro: se fijan al pagar y se borran si la lectura vuelve a pendiente
            if ($lectura->estado === self::PAGADO) {
                $lectura->fecha_pago ??= now();
                $lectura->monto_pagado ??= $lectura->monto;
                $lectura->cobrado_por ??= auth()->id();
            } else {
                $lectura->metodo_pago = null;
                $lectura->fecha_pago = null;
                $lectura->monto_pagado = null;
                $lectura->cobrado_por = null;
            }
        });

        // Cualquier cambio en una lectura cobrada actualiza Finanzas (agua cobrada del mes)
        static::saved(fn (Lectura $lectura) => $lectura->actualizarFinanzas());
        static::deleted(fn (Lectura $lectura) => $lectura->actualizarFinanzas());
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
            ->each(fn ($periodo) => BalanceMensual::recalcularAgua(...$periodo));
    }

    // Quién registró el cobro
    public function cobrador()
    {
        return $this->belongsTo(User::class, 'cobrado_por')->withTrashed();
    }

    // Relación: Una lectura pertenece a un Socio (Usuario)
    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * Monto en Bs.: lo cobrado si ya se pagó; si no, consumo × la tarifa de la lectura.
     */
    protected function monto(): Attribute
    {
        return Attribute::get(fn () => $this->estado === self::PAGADO && $this->monto_pagado !== null
            ? (float) $this->monto_pagado
            : round((float) $this->consumo * (float) ($this->tarifa_m3 ?? Ajuste::tarifaM3()), 2));
    }

    // Número que ordena los periodos (gestión y mes) para compararlos
    private static function ordenPeriodo(): string
    {
        return '(gestion * 12 + mes)';
    }

    /**
     * Lectura del socio en el periodo inmediatamente anterior (la última antes de mes/gestión).
     */
    public static function anteriorA(int $socioId, int $mes, int $gestion, ?int $ignorar = null): ?self
    {
        return static::where('user_id', $socioId)
            ->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar))
            ->whereRaw(self::ordenPeriodo().' < ?', [$gestion * 12 + $mes])
            ->orderByDesc('gestion')->orderByDesc('mes')
            ->first();
    }

    /**
     * Lectura del socio en el periodo inmediatamente siguiente (la primera después de mes/gestión).
     */
    public static function siguienteA(int $socioId, int $mes, int $gestion, ?int $ignorar = null): ?self
    {
        return static::where('user_id', $socioId)
            ->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar))
            ->whereRaw(self::ordenPeriodo().' > ?', [$gestion * 12 + $mes])
            ->orderBy('gestion')->orderBy('mes')
            ->first();
    }

    /**
     * Error si la lectura actual no encaja con la del mes siguiente ya registrado
     * (un mes atrasado o corregido): no puede superarla y, si ese mes ya se cobró,
     * debe ser igual a su lectura anterior. Null si está bien.
     */
    public static function errorConSiguiente(?self $siguiente, float $actual): ?string
    {
        if (! $siguiente) {
            return null;
        }

        if ($siguiente->estado === self::PAGADO) {
            return $actual !== (float) $siguiente->lectura_anterior
                ? "La lectura de {$siguiente->periodo} ya se cobró partiendo de ".self::m3($siguiente->lectura_anterior).': la lectura actual debe ser '.self::m3($siguiente->lectura_anterior).'.'
                : null;
        }

        return $actual > (float) $siguiente->lectura_actual
            ? "La lectura actual no puede ser mayor que la de {$siguiente->periodo} (".self::m3($siguiente->lectura_actual).').'
            : null;
    }

    // "1512 m³", "12.5 m³"
    public static function m3($valor): string
    {
        return rtrim(rtrim(number_format((float) $valor, 2, '.', ''), '0'), '.').' m³';
    }

    /**
     * Si se registró o corrigió un mes intermedio, la lectura del mes siguiente
     * (aún sin cobrar) parte de esta lectura actual.
     */
    public function encadenarSiguiente(): void
    {
        $siguiente = self::siguienteA($this->user_id, $this->mes, $this->gestion, $this->id);

        if ($siguiente && $siguiente->estado === self::PENDIENTE
            && (float) $siguiente->lectura_anterior !== (float) $this->lectura_actual) {
            $siguiente->update(['lectura_anterior' => $this->lectura_actual]);
        }
    }

    protected function mesNombre(): Attribute
    {
        return Attribute::get(fn () => Meses::nombre($this->mes));
    }

    // Ej: "Enero / 2026"
    protected function periodo(): Attribute
    {
        return Attribute::get(fn () => "{$this->mes_nombre} / {$this->gestion}");
    }

    public function scopeCronologico(Builder $query): Builder
    {
        return $query->orderBy('gestion')->orderBy('mes')->orderBy('id');
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', self::PENDIENTE);
    }

    // ---------- Historial ----------

    protected function moduloHistorial(): string
    {
        return 'lecturas';
    }

    protected function etiquetaHistorial(): string
    {
        return "la lectura de {$this->periodo} de ".($this->usuario->name ?? 'un socio');
    }

    protected function accionHistorial(string $evento, array $cambios): string
    {
        return ($cambios['estado']['despues'] ?? null) === self::PAGADO ? 'pago' : $evento;
    }

    protected function descripcionHistorial(string $accion): string
    {
        if ($accion === 'pago') {
            $via = match ($this->metodo_pago) {
                MetodoPago::Qr => ' por QR',
                MetodoPago::Efectivo => ' en efectivo',
                default => '',
            };

            return "Registró el pago{$via} de ".$this->etiquetaHistorial().' (Bs. '.number_format((float) $this->monto_pagado, 2, ',', '.').')';
        }

        return Actividad::ACCIONES[$accion].' '.$this->etiquetaHistorial();
    }
}
