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
        'estado',
        'metodo_pago',
    ];

    // Datos internos del cobro: el historial ya registra la acción de pago
    protected array $ignorarEnHistorial = ['fecha_pago', 'monto_pagado', 'cobrado_por'];

    protected function casts(): array
    {
        return [
            'mes' => 'integer',
            'gestion' => 'integer',
            'lectura_anterior' => 'decimal:2',
            'lectura_actual' => 'decimal:2',
            'consumo' => 'decimal:2',
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
     * Monto a cobrar en Bs. según la tarifa por m³ configurada.
     */
    protected function monto(): Attribute
    {
        return Attribute::get(fn () => round((float) $this->consumo * Ajuste::tarifaM3(), 2));
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
