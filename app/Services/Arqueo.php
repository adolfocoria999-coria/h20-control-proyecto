<?php

namespace App\Services;

use App\Enums\MetodoPago;
use App\Models\Lectura;
use App\Models\Multa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Arqueo de caja de un mes: todo lo cobrado (agua y multas) según la fecha en que
 * se cobró, separado por método de pago (QR / efectivo).
 */
class Arqueo
{
    public const SIN_METODO = 'sin_metodo';

    public readonly Carbon $desde;

    public readonly Carbon $hasta;

    private ?Collection $cobros = null;

    public function __construct(
        public readonly int $gestion,
        public readonly int $mes,
    ) {
        $this->desde = Carbon::create($gestion, $mes, 1)->startOfMonth();
        $this->hasta = $this->desde->copy()->endOfMonth();
    }

    /**
     * Cada cobro del mes, del más antiguo al más reciente.
     *
     * @return Collection<int, object{fecha: Carbon, tipo: string, concepto: string, socio: string, metodo: ?MetodoPago, cobrador: string, monto: float}>
     */
    public function cobros(): Collection
    {
        if ($this->cobros) {
            return $this->cobros;
        }

        $agua = Lectura::with(['usuario:id,name', 'cobrador:id,name'])
            ->where('estado', Lectura::PAGADO)
            ->whereBetween('fecha_pago', [$this->desde, $this->hasta])
            ->get()
            ->map(fn (Lectura $l) => (object) [
                'fecha' => $l->fecha_pago,
                'tipo' => 'agua',
                'concepto' => "Agua – {$l->periodo}",
                'socio' => $l->usuario->name ?? '—',
                'metodo' => $l->metodo_pago,
                'cobrador' => $l->cobrador->name ?? '—',
                'monto' => (float) $l->monto_pagado,
            ]);

        $multas = Multa::with(['socio:id,name', 'cobrador:id,name'])
            ->where('estado', Multa::PAGADO)
            ->whereBetween('fecha_pago', [$this->desde->toDateString(), $this->hasta->toDateString()])
            ->get()
            ->map(fn (Multa $m) => (object) [
                'fecha' => $m->fecha_pago,
                'tipo' => 'multa',
                'concepto' => "Multa – {$m->tipo_multa}",
                'socio' => $m->socio->name ?? '—',
                'metodo' => $m->metodo_pago,
                'cobrador' => $m->cobrador->name ?? '—',
                'monto' => (float) $m->monto,
            ]);

        return $this->cobros = $agua->concat($multas)->sortBy(fn ($c) => $c->fecha->timestamp)->values();
    }

    /**
     * Totales por concepto (agua / multas) y método (qr / efectivo / sin especificar).
     *
     * @return array{agua: array, multa: array, total: array}
     */
    public function resumen(): array
    {
        $vacio = ['qr' => 0.0, 'efectivo' => 0.0, self::SIN_METODO => 0.0, 'total' => 0.0, 'cantidad' => 0];
        $resumen = ['agua' => $vacio, 'multa' => $vacio, 'total' => $vacio];

        foreach ($this->cobros() as $cobro) {
            $metodo = $cobro->metodo?->value ?? self::SIN_METODO;

            foreach ([$cobro->tipo, 'total'] as $fila) {
                $resumen[$fila][$metodo] += $cobro->monto;
                $resumen[$fila]['total'] += $cobro->monto;
                $resumen[$fila]['cantidad']++;
            }
        }

        return $resumen;
    }

    // Hay cobros antiguos (anteriores al registro del método) sin QR/efectivo
    public function haySinMetodo(): bool
    {
        return $this->resumen()['total'][self::SIN_METODO] > 0;
    }
}
