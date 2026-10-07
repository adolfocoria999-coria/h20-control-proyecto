<?php

namespace App\Services;

use App\Models\Lectura;
use App\Models\Multa;
use App\Models\User;
use App\Support\Meses;
use Illuminate\Support\Collection;

/**
 * Indicadores de cobranza y morosidad de una gestión (año), opcionalmente de un solo mes.
 */
class ReporteFinanciero
{
    /** @var Collection<int, Lectura> */
    private Collection $lecturasGestion;

    /** @var Collection<int, Multa> */
    private Collection $multasGestion;

    public function __construct(
        public readonly int $gestion,
        public readonly ?int $mes = null,
    ) {
        $this->lecturasGestion = Lectura::where('gestion', $gestion)->get();
        $this->multasGestion = Multa::whereYear('fecha_multa', $gestion)->get();
    }

    /**
     * Totales cobrados y pendientes del periodo seleccionado.
     */
    public function totales(): array
    {
        $lecturas = $this->lecturasDelMes($this->mes);
        $multas = $this->multasDelMes($this->mes);

        $aguaCobrada = $this->sumarMontos($lecturas->where('estado', Lectura::PAGADO));
        $aguaPendiente = $this->sumarMontos($lecturas->where('estado', Lectura::PENDIENTE));
        $multasCobradas = (float) $multas->where('estado', Multa::PAGADO)->sum('monto');
        $multasPendientes = (float) $multas->where('estado', Multa::PENDIENTE)->sum('monto');

        return [
            'agua_cobrada' => $aguaCobrada,
            'agua_pendiente' => $aguaPendiente,
            'multas_cobradas' => $multasCobradas,
            'multas_pendientes' => $multasPendientes,
            'total_ingresado' => $aguaCobrada + $multasCobradas,
            'total_por_cobrar' => $aguaPendiente + $multasPendientes,
        ];
    }

    /**
     * Socios con lecturas o multas pendientes en el periodo, de mayor a menor deuda.
     *
     * @return Collection<int, User>
     */
    public function morosos(): Collection
    {
        $lecturasPendientes = $this->lecturasDelMes($this->mes)->where('estado', Lectura::PENDIENTE)->groupBy('user_id');
        $multasPendientes = $this->multasDelMes($this->mes)->where('estado', Multa::PENDIENTE)->groupBy('user_id');

        $ids = $lecturasPendientes->keys()->merge($multasPendientes->keys())->unique();

        // Incluye socios dados de baja: su deuda sigue pendiente
        return User::withTrashed()->whereIn('id', $ids)->get()
            ->map(function (User $socio) use ($lecturasPendientes, $multasPendientes) {
                $lecturas = $lecturasPendientes->get($socio->id, collect());
                $multas = $multasPendientes->get($socio->id, collect());

                // Meses de agua adeudados (0 si solo debe multas)
                $socio->cant_meses_mora = $lecturas->count();
                // Meses de agua adeudados, del más antiguo al más reciente (para los avisos)
                $socio->periodos_mora = $lecturas->sortBy(fn (Lectura $l) => $l->gestion * 100 + $l->mes)
                    ->map(fn (Lectura $l) => Meses::nombre($l->mes).' '.$l->gestion)
                    ->values()
                    ->all();
                $socio->cant_multas_mora = $multas->count();
                $socio->deuda_agua = $this->sumarMontos($lecturas);
                $socio->deuda_multas = (float) $multas->sum('monto');
                $socio->deuda_total = $socio->deuda_agua + $socio->deuda_multas;

                return $socio;
            })
            ->sortByDesc('deuda_total')
            ->values();
    }

    /**
     * Series del gráfico: un punto por mes de la gestión, o solo el mes seleccionado.
     */
    public function grafico(): array
    {
        $meses = $this->mes ? [$this->mes] : range(1, 12);

        $datos = ['meses' => [], 'agua_cobrada' => [], 'multas_cobradas' => [], 'pendientes' => []];

        foreach ($meses as $mes) {
            $lecturas = $this->lecturasDelMes($mes);
            $multas = $this->multasDelMes($mes);

            $datos['meses'][] = $this->mes ? Meses::nombre($mes) : mb_substr(Meses::nombre($mes), 0, 3);
            $datos['agua_cobrada'][] = $this->sumarMontos($lecturas->where('estado', Lectura::PAGADO));
            $datos['multas_cobradas'][] = (float) $multas->where('estado', Multa::PAGADO)->sum('monto');
            $datos['pendientes'][] = $this->sumarMontos($lecturas->where('estado', Lectura::PENDIENTE))
                + (float) $multas->where('estado', Multa::PENDIENTE)->sum('monto');
        }

        return $datos;
    }

    private function lecturasDelMes(?int $mes): Collection
    {
        return $mes ? $this->lecturasGestion->where('mes', $mes) : $this->lecturasGestion;
    }

    private function multasDelMes(?int $mes): Collection
    {
        return $mes
            ? $this->multasGestion->filter(fn (Multa $m) => $m->fecha_multa->month === $mes)
            : $this->multasGestion;
    }

    private function sumarMontos(Collection $lecturas): float
    {
        return (float) $lecturas->sum(fn (Lectura $l) => $l->monto);
    }
}
