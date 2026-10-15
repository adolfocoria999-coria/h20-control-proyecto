<?php

namespace App\Http\Controllers;

use App\Models\Lectura;
use App\Models\Multa;
use App\Models\User;
use App\Services\DocumentoPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Recibos en PDF de los pagos confirmados. Hacienda y el superadmin pueden
 * descargar cualquiera; el socio, solo los suyos.
 */
class ReciboController extends Controller
{
    public function agua(Request $request, Lectura $lectura)
    {
        $this->autorizar($request, $lectura->user_id, $lectura->estado === Lectura::PAGADO);

        $numero = 'AG-'.str_pad((string) $lectura->id, 6, '0', STR_PAD_LEFT);
        $socio = $lectura->usuario;

        return $this->descargar($numero, $socio, $lectura->fecha_pago, $lectura->metodo_pago?->etiqueta(), $lectura->cobrador?->name, (float) $lectura->monto_pagado, [
            'tipo' => "Consumo de agua – {$lectura->periodo}",
            'detalle' => [
                'Periodo' => $lectura->periodo,
                'Lectura anterior' => $this->m3($lectura->lectura_anterior),
                'Lectura actual' => $this->m3($lectura->lectura_actual),
                'Consumo' => $this->m3($lectura->consumo),
            ],
        ]);
    }

    public function multa(Request $request, Multa $multa)
    {
        $this->autorizar($request, $multa->user_id, $multa->estado === Multa::PAGADO);

        $numero = 'MU-'.str_pad((string) $multa->id, 6, '0', STR_PAD_LEFT);
        $socio = $multa->socio;

        return $this->descargar($numero, $socio, $multa->fecha_pago, $multa->metodo_pago?->etiqueta(), $multa->cobrador?->name, (float) $multa->monto, [
            'tipo' => "Multa – {$multa->tipo_multa}",
            'detalle' => array_filter([
                'Tipo de multa' => $multa->tipo_multa,
                'Motivo' => $multa->motivo,
                'Fecha de la multa' => $multa->fecha_multa?->format('d/m/Y'),
            ]),
        ]);
    }

    // Solo pagos confirmados; el socio no puede ver recibos de otros
    private function autorizar(Request $request, ?int $duenio, bool $pagado): void
    {
        abort_if($request->user()->esSocio() && $request->user()->id !== $duenio, 403, 'Este recibo no es tuyo.');
        abort_unless($pagado, 404, 'Este cobro todavía no está pagado.');
    }

    private function descargar(string $numero, ?User $socio, ?Carbon $fecha, ?string $metodo, ?string $cobrador, float $total, array $extra)
    {
        $r = $extra + [
            'numero' => $numero,
            'socio' => $socio?->name ?? '—',
            'ci' => $socio?->ci,
            'fecha' => $fecha?->format($fecha->format('H:i') === '00:00' ? 'd/m/Y' : 'd/m/Y H:i') ?? '—',
            'metodo' => $metodo ?? 'Sin especificar',
            'cobrador' => $cobrador ?? 'la Secretaría de Hacienda',
            'total' => $this->bs($total),
        ];

        return DocumentoPdf::descargar('pdf.recibo', ['r' => $r], "recibo_{$numero}.pdf");
    }

    private function bs(float $monto): string
    {
        return 'Bs. '.number_format($monto, 2, ',', '.');
    }

    private function m3($valor): string
    {
        return number_format((float) $valor, 2, ',', '.').' m³';
    }
}
