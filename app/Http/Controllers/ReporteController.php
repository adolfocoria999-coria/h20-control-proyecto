<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AvisoDeuda;
use App\Services\CsvExporter;
use App\Services\Historial;
use App\Services\ReporteFinanciero;
use App\Support\Meses;
use App\Support\WhatsApp;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    public function index(Request $request)
    {
        $reporte = $this->reporteDesde($request);

        $totales = $reporte->totales();
        $grafico = $reporte->grafico();

        return view('reportes.index', [
            'mesSel' => $reporte->mes ?? '',
            'gestion' => $reporte->gestion,
            'gestiones' => range((int) date('Y'), (int) date('Y') - 4),
            'totalAguaCobrada' => $totales['agua_cobrada'],
            'totalAguaPendiente' => $totales['agua_pendiente'],
            'totalMultasCobradas' => $totales['multas_cobradas'],
            'totalMultasPendientes' => $totales['multas_pendientes'],
            'totalGeneralIngresado' => $totales['total_ingresado'],
            'totalGeneralPorCobrar' => $totales['total_por_cobrar'],
            'sociosMorosos' => $reporte->morosos(),
            'mesesGrafico' => $grafico['meses'],
            'datosAguaCobrada' => $grafico['agua_cobrada'],
            'datosMultasCobradas' => $grafico['multas_cobradas'],
            'datosPendientes' => $grafico['pendientes'],
        ]);
    }

    public function exportar(Request $request)
    {
        $reporte = $this->reporteDesde($request);

        $filas = $reporte->morosos()->map(fn ($socio) => [
            $socio->name,
            $socio->ci ?? 'Sin CI',
            match ($socio->cant_meses_mora) {
                0 => 'Solo multas',
                1 => '1 Mes',
                default => $socio->cant_meses_mora.' Meses',
            },
            CsvExporter::dinero($socio->deuda_agua),
            CsvExporter::dinero($socio->deuda_multas),
            CsvExporter::dinero($socio->deuda_total),
            AvisoDeuda::esCorte($socio) ? 'Corte de Agua' : 'Notificar',
        ]);

        $nombre = 'deudores_otb_'.($reporte->mes ? "mes_{$reporte->mes}_" : 'todos_')."gestion_{$reporte->gestion}.csv";

        Historial::registrar('exportacion', 'reportes', 'Exportó la lista de deudores de la gestión '.$reporte->gestion.' a Excel');

        return CsvExporter::descargar(
            $nombre,
            ['Socio / Afiliado', 'CI', 'Meses en Mora', 'Monto Agua (Bs.)', 'Monto Multas (Bs.)', 'Total Acumulado (Bs.)', 'Acción Recomendada'],
            $filas
        );
    }

    /**
     * Abre WhatsApp con el aviso de deuda (o de corte) ya escrito para el socio.
     * Pasa por el servidor para armar el mensaje con los montos del reporte y
     * dejar constancia en el historial de quién avisó y cuándo.
     */
    public function notificar(Request $request, User $socio)
    {
        $reporte = $this->reporteDesde($request);
        $moroso = $reporte->morosos()->firstWhere('id', $socio->id);

        if (! $moroso) {
            return back()->with('error', "{$socio->name} no tiene deudas pendientes en el periodo seleccionado.");
        }

        $enlace = WhatsApp::enlace($socio->telefono, AvisoDeuda::mensaje($moroso));

        if (! $enlace) {
            return back()->with('error', "{$socio->name} no tiene un número de celular válido registrado.");
        }

        $tipo = AvisoDeuda::esCorte($moroso) ? 'aviso de corte' : 'recordatorio de deuda';
        Historial::registrar(
            'notificacion',
            'reportes',
            "Envió por WhatsApp un {$tipo} a {$socio->name} (".WhatsApp::numero($socio->telefono).') por Bs. '.number_format($moroso->deuda_total, 2, ',', '.'),
            $socio,
        );

        return redirect()->away($enlace);
    }

    private function reporteDesde(Request $request): ReporteFinanciero
    {
        return new ReporteFinanciero(
            gestion: $request->integer('gestion') ?: (int) date('Y'),
            mes: Meses::numero($request->get('mes')),
        );
    }
}
