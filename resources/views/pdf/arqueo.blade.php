{{-- Acta de arqueo de caja mensual, con espacio para firmas --}}
@extends('pdf.layout')

@php
    use App\Services\Arqueo;
    $bs = fn ($monto) => 'Bs. '.number_format($monto, 2, ',', '.');
@endphp

@section('titulo', "Arqueo de caja – {$periodo}")

@section('contenido')
    <h1>Acta de Arqueo de Caja</h1>
    <div class="subtitulo">{{ $periodo }} · Cobros de agua y multas según la fecha de cobro</div>

    <table class="tabla">
        <thead>
            <tr>
                <th>Concepto</th>
                <th class="centro">Cobros</th>
                <th class="der">QR</th>
                <th class="der">Efectivo</th>
                @if($conSinMetodo)<th class="der">Sin especificar</th>@endif
                <th class="der">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach(['agua' => 'Agua', 'multa' => 'Multas'] as $clave => $nombre)
                <tr>
                    <td>{{ $nombre }}</td>
                    <td class="centro">{{ $resumen[$clave]['cantidad'] }}</td>
                    <td class="der">{{ $bs($resumen[$clave]['qr']) }}</td>
                    <td class="der">{{ $bs($resumen[$clave]['efectivo']) }}</td>
                    @if($conSinMetodo)<td class="der">{{ $bs($resumen[$clave][Arqueo::SIN_METODO]) }}</td>@endif
                    <td class="der">{{ $bs($resumen[$clave]['total']) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>TOTAL RECAUDADO</td>
                <td class="centro">{{ $resumen['total']['cantidad'] }}</td>
                <td class="der">{{ $bs($resumen['total']['qr']) }}</td>
                <td class="der">{{ $bs($resumen['total']['efectivo']) }}</td>
                @if($conSinMetodo)<td class="der">{{ $bs($resumen['total'][Arqueo::SIN_METODO]) }}</td>@endif
                <td class="der">{{ $bs($resumen['total']['total']) }}</td>
            </tr>
        </tfoot>
    </table>

    <table class="caja" style="margin-top: 12px;">
        <tr>
            <td>
                <div class="tenue">Efectivo que debe haber en caja</div>
                <div class="monto-grande">{{ $bs($resumen['total']['efectivo']) }}</div>
            </td>
            <td>
                <div class="tenue">Depositado por QR en la cuenta de la OTB</div>
                <div class="monto-grande">{{ $bs($resumen['total']['qr']) }}</div>
            </td>
            <td>
                <div class="tenue">Efectivo contado</div>
                <div style="margin-top: 12px;">Bs. ______________</div>
            </td>
        </tr>
    </table>

    <h1 style="font-size: 12px; margin-top: 18px;">Detalle de cobros</h1>
    <table class="tabla">
        <thead>
            <tr>
                <th style="width: 11%;">Fecha</th>
                <th>Concepto</th>
                <th>Socio</th>
                <th style="width: 11%;">Método</th>
                <th>Cobrado por</th>
                <th class="der" style="width: 13%;">Monto</th>
            </tr>
        </thead>
        <tbody>
            @forelse($cobros as $c)
                <tr>
                    <td>{{ $c->fecha->format('d/m/Y') }}</td>
                    <td>{{ $c->concepto }}</td>
                    <td>{{ $c->socio }}</td>
                    <td>{{ $c->metodo?->etiqueta() ?? 'Sin especificar' }}</td>
                    <td>{{ $c->cobrador }}</td>
                    <td class="der">{{ $bs($c->monto) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="centro tenue">No hubo cobros en {{ $periodo }}.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="firmas">
        <tr>
            <td><div class="linea-firma">Secretario(a) de Hacienda</div></td>
            <td><div class="linea-firma">Presidente(a) de la OTB</div></td>
        </tr>
    </table>
@endsection
