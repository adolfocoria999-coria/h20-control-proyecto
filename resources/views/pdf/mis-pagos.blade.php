{{-- Historial de pagos del socio (mismos filtros que la pantalla Mis Pagos) --}}
@extends('pdf.layout')

@php $bs = fn ($monto) => 'Bs. '.number_format($monto, 2, ',', '.'); @endphp

@section('titulo', 'Historial de pagos')

@section('contenido')
    <h1>Historial de Pagos</h1>
    <div class="subtitulo">{{ $socio->name }}@if($socio->ci) · C.I. {{ $socio->ci }}@endif · {{ $periodo }}</div>

    <table class="caja" style="margin-bottom: 12px;">
        <tr>
            <td><div class="tenue">Pagos</div><div class="negrita">{{ $totales['cantidad'] }}</div></td>
            <td><div class="tenue">Por QR</div><div class="negrita">{{ $bs($totales['qr']) }}</div></td>
            <td><div class="tenue">En efectivo</div><div class="negrita">{{ $bs($totales['efectivo']) }}</div></td>
            <td class="der"><div class="tenue">Total pagado</div><div class="monto-grande">{{ $bs($totales['total']) }}</div></td>
        </tr>
    </table>

    <table class="tabla">
        <thead>
            <tr>
                <th style="width: 18%;">Fecha de pago</th>
                <th>Concepto</th>
                <th style="width: 13%;">Método</th>
                <th style="width: 20%;">Registrado por</th>
                <th class="der" style="width: 14%;">Monto</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pagos as $pago)
                <tr>
                    <td>{{ $pago->fecha->format($pago->con_hora ? 'd/m/Y H:i' : 'd/m/Y') }}</td>
                    <td>{{ $pago->concepto }}</td>
                    <td>{{ $pago->metodo?->etiqueta() ?? 'Sin especificar' }}</td>
                    <td>{{ $pago->registrado_por }}</td>
                    <td class="der">{{ $bs($pago->monto) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="centro tenue">No hay pagos registrados en este periodo.</td></tr>
            @endforelse
        </tbody>
        @if($pagos->isNotEmpty())
            <tfoot>
                <tr><td colspan="4">TOTAL</td><td class="der">{{ $bs($totales['total']) }}</td></tr>
            </tfoot>
        @endif
    </table>
@endsection
