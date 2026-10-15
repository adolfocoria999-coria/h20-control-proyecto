{{-- Recibo de un pago confirmado (agua o multa). $r viene de ReciboController::datos() --}}
@extends('pdf.layout')

@section('titulo', "Recibo {$r['numero']}")

@section('contenido')
    <table>
        <tr>
            <td>
                <h1>Recibo de Pago</h1>
                <div class="subtitulo">{{ $r['tipo'] }}</div>
            </td>
            <td class="der" style="vertical-align: top;">
                <div class="tenue">N.º de recibo</div>
                <div style="font-size: 15px; font-weight: bold;">{{ $r['numero'] }}</div>
                <div style="margin-top: 6px;"><span class="sello">PAGADO</span></div>
            </td>
        </tr>
    </table>

    <table class="caja" style="margin-top: 8px;">
        <tr>
            <td style="width: 50%;">
                <div class="tenue">Socio</div>
                <div class="negrita" style="font-size: 12px;">{{ $r['socio'] }}</div>
                @if($r['ci'])<div>C.I.: {{ $r['ci'] }}</div>@endif
            </td>
            <td style="width: 50%;">
                <div class="tenue">Fecha de pago</div>
                <div class="negrita">{{ $r['fecha'] }}</div>
                <div>Método: {{ $r['metodo'] }}</div>
            </td>
        </tr>
    </table>

    <table class="tabla" style="margin-top: 14px;">
        <thead>
            <tr><th>Detalle</th><th class="der">Valor</th></tr>
        </thead>
        <tbody>
            @foreach($r['detalle'] as $concepto => $valor)
                <tr><td>{{ $concepto }}</td><td class="der">{{ $valor }}</td></tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td>TOTAL PAGADO</td><td class="der">{{ $r['total'] }}</td></tr>
        </tfoot>
    </table>

    <p style="margin-top: 14px;">Son: <span class="negrita">{{ $r['total'] }}</span>, recibidos por {{ $r['cobrador'] }}.</p>

    <table class="firmas">
        <tr>
            <td><div class="linea-firma">Recibí conforme – Secretaría de Hacienda<br>{{ $r['cobrador'] }}</div></td>
            <td><div class="linea-firma">Socio<br>{{ $r['socio'] }}</div></td>
        </tr>
    </table>

    <p class="tenue centro" style="margin-top: 30px;">
        Este recibo se genera desde el sistema H2O Control con los datos del pago registrado por la OTB.
    </p>
@endsection
