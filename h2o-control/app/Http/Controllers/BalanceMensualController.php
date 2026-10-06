<?php

namespace App\Http\Controllers;

use App\Http\Requests\BalanceMensualRequest;
use App\Models\BalanceMensual;
use App\Services\CsvExporter;
use App\Services\Historial;
use App\Services\QrPago;
use App\Support\Meses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BalanceMensualController extends Controller
{
    public function index(Request $request)
    {
        $gestion = $request->integer('gestion') ?: null;
        $mes = Meses::numero($request->get('mes'));

        $balances = $this->consultaFiltrada($gestion, $mes)->cronologico()->get();

        // Balance total acumulado en caja (sin filtros)
        $cajaActual = (float) BalanceMensual::sum('saldo_final');

        // Tarjetas: totales de la lista filtrada
        $totalIngresos = $balances->sum('ingresos_totales');
        $totalEgresos = $balances->sum('egresos');

        $periodo = match (true) {
            $gestion && $mes => Meses::nombre($mes)." $gestion",
            (bool) $gestion => "Gestión $gestion",
            (bool) $mes => 'Mes '.Meses::nombre($mes).' - Todas las Gestiones',
            default => null,
        };
        $etiquetaIngresos = $periodo ? "Ingresos ($periodo)" : 'Ingresos Históricos';
        $etiquetaGastos = $periodo ? "Gastos ($periodo)" : 'Gastos Históricos';

        // Datos del gráfico
        $grafico = [
            'meses' => $balances->map(fn ($b) => "{$b->mes_nombre} {$b->gestion}")->values(),
            'ingresos' => $balances->map(fn ($b) => $b->ingresos_totales)->values(),
            'egresos' => $balances->map(fn ($b) => (float) $b->egresos)->values(),
        ];

        return view('finanzas.index', compact(
            'balances',
            'mes',
            'cajaActual',
            'totalIngresos',
            'totalEgresos',
            'etiquetaIngresos',
            'etiquetaGastos',
            'grafico'
        ) + ['qr' => QrPago::actual()]);
    }

    public function create()
    {
        return view('finanzas.create');
    }

    public function store(BalanceMensualRequest $request)
    {
        $datos = $request->safe()->except('comprobante');

        if ($request->hasFile('comprobante')) {
            $datos['comprobante_url'] = $request->file('comprobante')->store('comprobantes', 'local');
        }

        // El saldo final lo calcula el modelo
        BalanceMensual::create($datos);

        return redirect()->route('finanzas.index')->with('success', 'Balance registrado ✓');
    }

    public function edit(BalanceMensual $finanza)
    {
        return view('finanzas.edit', ['balance' => $finanza]);
    }

    public function update(BalanceMensualRequest $request, BalanceMensual $finanza)
    {
        $datos = $request->safe()->except('comprobante');

        // Si sube un archivo nuevo, eliminamos el anterior del almacenamiento local
        if ($request->hasFile('comprobante')) {
            if ($finanza->comprobante_url) {
                Storage::disk('local')->delete($finanza->comprobante_url);
            }
            $datos['comprobante_url'] = $request->file('comprobante')->store('comprobantes', 'local');
        }

        $finanza->update($datos);

        return redirect()->route('finanzas.index')->with('success', 'Balance actualizado ✓');
    }

    public function destroy(BalanceMensual $finanza)
    {
        // Solo el superadmin puede eliminar balances (igual que en la vista)
        abort_unless(auth()->user()->esSuperAdmin(), 403, 'Solo el superadministrador puede eliminar balances.');

        if ($finanza->comprobante_url) {
            Storage::disk('local')->delete($finanza->comprobante_url);
        }

        $finanza->delete();

        return redirect()->route('finanzas.index')->with('success', 'Balance eliminado ✓');
    }

    /**
     * Muestra el comprobante (disco privado) solo a usuarios con sesión.
     * Con ?descargar=1 se descarga en lugar de abrirse en el navegador.
     */
    public function comprobante(Request $request, BalanceMensual $finanza)
    {
        $ruta = $finanza->comprobante_url;

        abort_unless($ruta && Storage::disk('local')->exists($ruta), 404, 'Este balance no tiene comprobante.');

        $nombre = "comprobante_{$finanza->mes_nombre}_{$finanza->gestion}.".pathinfo($ruta, PATHINFO_EXTENSION);

        return $request->boolean('descargar')
            ? Storage::disk('local')->download($ruta, $nombre)
            : Storage::disk('local')->response($ruta, $nombre);
    }

    public function exportar(Request $request)
    {
        $filas = $this->consultaFiltrada($request->integer('gestion') ?: null, Meses::numero($request->get('mes')))
            ->orderBy('gestion', 'desc')
            ->orderBy('mes', 'desc')
            ->lazy()
            ->map(fn (BalanceMensual $b) => [
                $b->id,
                $b->mes_nombre,
                $b->gestion,
                CsvExporter::dinero($b->ingresos),
                CsvExporter::dinero($b->ingresos_multas),
                CsvExporter::dinero($b->egresos),
                CsvExporter::dinero($b->saldo_final),
                $b->detalle ?? 'Sin observaciones',
            ]);

        Historial::registrar('exportacion', 'finanzas', 'Exportó los balances de finanzas a Excel');

        return CsvExporter::descargar(
            'finanzas_otb_'.date('Y-m-d_H-i').'.csv',
            ['ID', 'Mes', 'Gestión', 'Ingresos (Bs.)', 'Multas cobradas (Bs.)', 'Egresos (Bs.)', 'Saldo Final (Bs.)', 'Detalles'],
            $filas
        );
    }

    private function consultaFiltrada(?int $gestion, ?int $mes): Builder
    {
        return BalanceMensual::query()
            ->when($gestion, fn ($q) => $q->where('gestion', $gestion))
            ->when($mes, fn ($q) => $q->where('mes', $mes));
    }
}
