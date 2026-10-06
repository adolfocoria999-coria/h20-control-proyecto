<?php

namespace App\Http\Controllers;

use App\Services\Arqueo;
use App\Services\CsvExporter;
use App\Services\Historial;
use App\Support\Meses;
use Illuminate\Http\Request;

/**
 * Arqueo de caja mensual: lo cobrado por QR y en efectivo (agua y multas).
 */
class ArqueoController extends Controller
{
    public function index(Request $request)
    {
        $arqueo = $this->arqueoDesde($request);

        return view('finanzas.arqueo', [
            'arqueo' => $arqueo,
            'resumen' => $arqueo->resumen(),
            'cobros' => $arqueo->cobros(),
            'gestiones' => range((int) date('Y'), (int) date('Y') - 4),
        ]);
    }

    public function exportar(Request $request)
    {
        $arqueo = $this->arqueoDesde($request);
        $periodo = Meses::nombre($arqueo->mes).' '.$arqueo->gestion;
        $resumen = $arqueo->resumen();

        $detalle = $arqueo->cobros()->map(fn ($c) => [
            $c->fecha->format('d/m/Y'),
            $c->tipo === 'agua' ? 'Agua' : 'Multa',
            $c->concepto,
            $c->socio,
            $c->metodo?->etiqueta() ?? 'Sin especificar',
            $c->cobrador,
            CsvExporter::dinero($c->monto),
        ]);

        // Resumen al final del archivo: es lo que se presenta en el arqueo
        $fila = fn (string $concepto, array $t) => ['', '', $concepto, '', 'QR: '.CsvExporter::dinero($t['qr']), 'Efectivo: '.CsvExporter::dinero($t['efectivo']), CsvExporter::dinero($t['total'])];
        $pie = collect([
            [],
            ['', '', "RESUMEN DEL ARQUEO – {$periodo}", '', '', '', ''],
            $fila('Agua', $resumen['agua']),
            $fila('Multas', $resumen['multa']),
            $fila('TOTAL RECAUDADO', $resumen['total']),
        ]);
        if ($arqueo->haySinMetodo()) {
            $pie->push(['', '', 'Cobros sin método registrado (anteriores al sistema de arqueo)', '', '', '', CsvExporter::dinero($resumen['total'][Arqueo::SIN_METODO])]);
        }

        Historial::registrar('exportacion', 'finanzas', "Descargó el arqueo de caja de {$periodo}");

        return CsvExporter::descargar(
            'arqueo_'.$arqueo->gestion.'_'.str_pad((string) $arqueo->mes, 2, '0', STR_PAD_LEFT).'.csv',
            ['Fecha de cobro', 'Tipo', 'Concepto', 'Socio', 'Método', 'Cobrado por', 'Monto (Bs.)'],
            $detalle->concat($pie)
        );
    }

    private function arqueoDesde(Request $request): Arqueo
    {
        return new Arqueo(
            gestion: $request->integer('gestion') ?: (int) date('Y'),
            mes: Meses::numero($request->get('mes')) ?? (int) date('n'),
        );
    }
}
