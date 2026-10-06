<?php

namespace App\Services;

use App\Enums\MetodoPago;
use App\Models\Lectura;
use App\Models\Multa;
use App\Models\User;
use App\Support\Meses;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Historial de pagos confirmados de un socio (agua y multas), con filtros.
 * Une ambas tablas en una sola consulta SQL (UNION) para que la base de datos
 * resuelva el orden y la paginación aunque el historial sea largo.
 */
class PagosDelSocio
{
    public const POR_PAGINA = 20;

    /**
     * @param  array{gestion: ?int, mes: ?int, metodo: ?string, tipo: ?string}  $filtros
     */
    public function __construct(
        private readonly User $socio,
        public readonly array $filtros,
    ) {}

    public function paginados(): LengthAwarePaginator
    {
        $pagina = $this->union()
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        $cobradores = User::withTrashed()->whereIn('id', collect($pagina->items())->pluck('cobrado_por')->filter())->pluck('name', 'id');

        return $pagina->through(fn ($fila) => $this->presentar($fila, $cobradores));
    }

    /**
     * Totales del periodo filtrado: total, por QR y en efectivo.
     *
     * @return array{total: float, qr: float, efectivo: float, cantidad: int}
     */
    public function totales(): array
    {
        $porMetodo = DB::query()->fromSub($this->union(), 'pagos')
            ->selectRaw('metodo_pago, sum(monto) as total, count(*) as cantidad')
            ->groupBy('metodo_pago')
            ->get()
            ->keyBy('metodo_pago');

        return [
            'total' => (float) $porMetodo->sum('total'),
            'qr' => (float) ($porMetodo[MetodoPago::Qr->value]->total ?? 0),
            'efectivo' => (float) ($porMetodo[MetodoPago::Efectivo->value]->total ?? 0),
            'cantidad' => (int) $porMetodo->sum('cantidad'),
        ];
    }

    /**
     * Todos los pagos filtrados (para exportar).
     */
    public function todos(): Collection
    {
        $filas = $this->union()->orderByDesc('fecha')->orderByDesc('id')->get();
        $cobradores = User::withTrashed()->whereIn('id', $filas->pluck('cobrado_por')->filter())->pluck('name', 'id');

        return $filas->map(fn ($fila) => $this->presentar($fila, $cobradores));
    }

    // Años en los que el socio tiene pagos (para el filtro)
    public function gestiones(): array
    {
        $anios = $this->union(conFiltros: false)->pluck('fecha')
            ->map(fn ($fecha) => (int) substr((string) $fecha, 0, 4))
            ->push((int) date('Y'))
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $anios;
    }

    private function union(bool $conFiltros = true): Builder
    {
        $tipo = $conFiltros ? $this->filtros['tipo'] : null;

        $agua = DB::table('lecturas')
            ->selectRaw("'agua' as tipo, id, fecha_pago as fecha, metodo_pago, monto_pagado as monto, cobrado_por, mes, gestion, null as tipo_multa")
            ->where('user_id', $this->socio->id)
            ->where('estado', Lectura::PAGADO);

        $multas = DB::table('multas')
            ->selectRaw("'multa' as tipo, id, fecha_pago as fecha, metodo_pago, monto, cobrado_por, null as mes, null as gestion, tipo_multa")
            ->where('user_id', $this->socio->id)
            ->where('estado', Multa::PAGADO);

        if ($conFiltros) {
            foreach ([$agua, $multas] as $consulta) {
                $this->aplicarFiltros($consulta);
            }
        }

        if ($tipo === 'agua') {
            return DB::query()->fromSub($agua, 'pagos');
        }
        if ($tipo === 'multa') {
            return DB::query()->fromSub($multas, 'pagos');
        }

        return DB::query()->fromSub($agua->unionAll($multas), 'pagos');
    }

    private function aplicarFiltros(Builder $consulta): void
    {
        ['gestion' => $gestion, 'mes' => $mes, 'metodo' => $metodo] = $this->filtros;

        if ($gestion) {
            $desde = $mes ? Carbon::create($gestion, $mes, 1) : Carbon::create($gestion, 1, 1);
            $hasta = $mes ? $desde->copy()->endOfMonth() : $desde->copy()->endOfYear();
            // Rango de fechas (no YEAR()/MONTH()) para aprovechar el índice (estado, fecha_pago)
            $consulta->whereBetween('fecha_pago', [$desde->format('Y-m-d 00:00:00'), $hasta->format('Y-m-d 23:59:59')]);
        }

        if ($metodo) {
            $consulta->where('metodo_pago', $metodo);
        }
    }

    private function presentar(object $fila, Collection $cobradores): object
    {
        return (object) [
            'tipo' => $fila->tipo,
            'fecha' => Carbon::parse($fila->fecha),
            'concepto' => $fila->tipo === 'agua'
                ? 'Agua – '.Meses::nombre((int) $fila->mes).' '.$fila->gestion
                : 'Multa – '.$fila->tipo_multa,
            'metodo' => $fila->metodo_pago ? MetodoPago::from($fila->metodo_pago) : null,
            'monto' => (float) $fila->monto,
            'registrado_por' => $cobradores[$fila->cobrado_por] ?? '—',
            // Los pagos de agua guardan la hora; las multas, solo la fecha
            'con_hora' => $fila->tipo === 'agua',
        ];
    }
}
