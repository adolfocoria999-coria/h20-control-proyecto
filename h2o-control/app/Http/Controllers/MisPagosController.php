<?php

namespace App\Http\Controllers;

use App\Enums\MetodoPago;
use App\Services\CsvExporter;
use App\Services\Historial;
use App\Services\PagosDelSocio;
use App\Support\Meses;
use Illuminate\Http\Request;

/**
 * Historial de pagos del socio que inició sesión (lo que Hacienda le confirmó).
 */
class MisPagosController extends Controller
{
    public function index(Request $request)
    {
        $pagos = $this->pagos($request);

        return view('socio.pagos', [
            'pagos' => $pagos->paginados(),
            'totales' => $pagos->totales(),
            'gestiones' => $pagos->gestiones(),
            'filtros' => $pagos->filtros,
        ]);
    }

    public function exportar(Request $request)
    {
        $pagos = $this->pagos($request);

        $filas = $pagos->todos()->map(fn ($p) => [
            $p->fecha->format($p->con_hora ? 'd/m/Y H:i' : 'd/m/Y'),
            $p->concepto,
            $p->metodo?->etiqueta() ?? 'Sin especificar',
            CsvExporter::dinero($p->monto),
            $p->registrado_por,
        ]);

        Historial::registrar('exportacion', 'usuarios', 'Exportó su historial de pagos a Excel');

        return CsvExporter::descargar(
            'mis_pagos_'.date('Y-m-d').'.csv',
            ['Fecha de pago', 'Concepto', 'Método', 'Monto (Bs.)', 'Registrado por'],
            $filas
        );
    }

    private function pagos(Request $request): PagosDelSocio
    {
        $metodo = MetodoPago::tryFrom((string) $request->get('metodo'));
        $tipo = in_array($request->get('tipo'), ['agua', 'multa'], true) ? $request->get('tipo') : null;

        return new PagosDelSocio($request->user(), [
            // Sin parámetro: el año actual. "todas" = todo el historial.
            'gestion' => $request->has('gestion') ? ($request->integer('gestion') ?: null) : (int) date('Y'),
            'mes' => Meses::numero($request->get('mes')),
            'metodo' => $metodo?->value,
            'tipo' => $tipo,
        ]);
    }
}
