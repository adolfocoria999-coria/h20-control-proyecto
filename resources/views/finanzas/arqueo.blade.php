@php
    use App\Services\Arqueo;
    use App\Support\Meses;

    $periodo = Meses::nombre($arqueo->mes).' '.$arqueo->gestion;
    $bs = fn ($monto) => 'Bs. '.number_format($monto, 2, ',', '.');
    $conSinMetodo = $arqueo->haySinMetodo();
    $input = 'w-full px-4 py-2.5 bg-white rounded-xl border border-sky-300 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500/50 text-sm font-semibold text-blue-950 shadow-sm cursor-pointer';
@endphp

<x-app-layout>
    <div class="py-10 bg-sky-200/70 min-h-screen print:bg-white print:py-0">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Encabezado -->
            <div class="md:flex md:items-center md:justify-between gap-4">
                <div>
                    <a href="{{ route('finanzas.index') }}" class="text-xs font-bold text-blue-700 hover:underline print:hidden">← Volver a Finanzas</a>
                    <h2 class="font-extrabold text-3xl text-blue-950 leading-tight">Arqueo de Caja – {{ $periodo }}</h2>
                    <p class="text-sm text-sky-900 mt-1 font-bold">Recaudación del mes por QR y en efectivo (agua y multas), según la fecha de cobro</p>
                </div>
                <div class="mt-4 md:mt-0 flex flex-wrap gap-3 print:hidden">
                    <x-boton-excel :href="route('arqueo.exportar', ['gestion' => $arqueo->gestion, 'mes' => $arqueo->mes])" />
                    <button type="button" onclick="window.print()"
                            class="inline-flex items-center justify-center gap-2 whitespace-nowrap bg-white hover:bg-sky-50 text-blue-900 border-2 border-blue-200 px-5 py-3 rounded-xl font-extrabold text-sm shadow-md">
                        🖨️ Imprimir
                    </button>
                </div>
            </div>

            <!-- Periodo -->
            <form method="GET" action="{{ route('arqueo.index') }}" class="bg-white p-5 rounded-2xl shadow-md border border-sky-100 grid grid-cols-2 sm:grid-cols-4 gap-4 print:hidden">
                <div>
                    <label for="mes" class="block text-xs font-black uppercase text-blue-950 tracking-wider mb-1.5 ml-1">Mes</label>
                    <select name="mes" id="mes" onchange="this.form.submit()" class="{{ $input }}">
                        @foreach(Meses::NOMBRES as $num => $nombre)
                            <option value="{{ $num }}" {{ $arqueo->mes === $num ? 'selected' : '' }}>{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="gestion" class="block text-xs font-black uppercase text-blue-950 tracking-wider mb-1.5 ml-1">Gestión</label>
                    <select name="gestion" id="gestion" onchange="this.form.submit()" class="{{ $input }}">
                        @foreach($gestiones as $anio)
                            <option value="{{ $anio }}" {{ $arqueo->gestion === $anio ? 'selected' : '' }}>{{ $anio }}</option>
                        @endforeach
                    </select>
                </div>
            </form>

            <!-- Totales -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl p-5 shadow-md border-l-8 border-blue-700">
                    <p class="text-xs font-black uppercase tracking-wider text-sky-700">Total recaudado</p>
                    <p class="mt-1 text-3xl font-black text-blue-950">{{ $bs($resumen['total']['total']) }}</p>
                    <p class="text-xs font-bold text-gray-500">{{ $resumen['total']['cantidad'] }} cobro(s)</p>
                </div>
                <div class="bg-white rounded-2xl p-5 shadow-md border-l-8 border-sky-500">
                    <p class="text-xs font-black uppercase tracking-wider text-sky-700">📱 Por QR</p>
                    <p class="mt-1 text-3xl font-black text-sky-700">{{ $bs($resumen['total']['qr']) }}</p>
                    <p class="text-xs font-bold text-gray-500">Debe coincidir con el extracto bancario</p>
                </div>
                <div class="bg-white rounded-2xl p-5 shadow-md border-l-8 border-emerald-500">
                    <p class="text-xs font-black uppercase tracking-wider text-sky-700">💵 En efectivo</p>
                    <p class="mt-1 text-3xl font-black text-emerald-700">{{ $bs($resumen['total']['efectivo']) }}</p>
                    <p class="text-xs font-bold text-gray-500">Debe coincidir con el dinero en caja</p>
                </div>
            </div>

            @if($conSinMetodo)
                <div class="p-4 bg-amber-50 border-l-4 border-amber-500 rounded-r-xl text-sm font-bold text-amber-900">
                    ⚠️ {{ $bs($resumen['total'][Arqueo::SIN_METODO]) }} corresponden a cobros registrados antes de que el sistema pidiera el método de pago (QR o efectivo).
                </div>
            @endif

            <!-- Resumen por concepto -->
            <div class="bg-white rounded-2xl shadow-md border border-sky-100 overflow-hidden">
                <h3 class="px-5 pt-5 text-lg font-black text-blue-950">Resumen</h3>
                <div class="overflow-x-auto">
                    <table class="tabla-responsiva min-w-full mt-3 text-sm">
                        <thead class="bg-blue-900 text-white text-xs font-bold uppercase tracking-wider">
                            <tr>
                                <th class="px-5 py-3 text-left">Concepto</th>
                                <th class="px-5 py-3 text-right">📱 QR</th>
                                <th class="px-5 py-3 text-right">💵 Efectivo</th>
                                @if($conSinMetodo)
                                    <th class="px-5 py-3 text-right">Sin especificar</th>
                                @endif
                                <th class="px-5 py-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sky-100">
                            @foreach(['agua' => '💧 Agua', 'multa' => '⚖️ Multas', 'total' => 'TOTAL'] as $clave => $nombre)
                                @php $fila = $resumen[$clave]; @endphp
                                <tr class="{{ $clave === 'total' ? 'bg-sky-50 font-black' : 'font-bold' }} text-blue-950">
                                    <td data-label="Concepto" class="px-5 py-3">{{ $nombre }} <span class="text-xs font-semibold text-gray-500">({{ $fila['cantidad'] }})</span></td>
                                    <td data-label="📱 QR" class="px-5 py-3 text-right whitespace-nowrap">{{ $bs($fila['qr']) }}</td>
                                    <td data-label="💵 Efectivo" class="px-5 py-3 text-right whitespace-nowrap">{{ $bs($fila['efectivo']) }}</td>
                                    @if($conSinMetodo)
                                        <td data-label="Sin especificar" class="px-5 py-3 text-right whitespace-nowrap">{{ $bs($fila[Arqueo::SIN_METODO]) }}</td>
                                    @endif
                                    <td data-label="Total" class="px-5 py-3 text-right whitespace-nowrap">{{ $bs($fila['total']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Detalle de cobros -->
            <div class="bg-white rounded-2xl shadow-md border border-sky-100 overflow-hidden">
                <h3 class="px-5 pt-5 text-lg font-black text-blue-950">Detalle de cobros</h3>
                <div class="overflow-x-auto">
                    <table class="tabla-responsiva min-w-full mt-3 text-sm">
                        <thead class="bg-blue-900 text-white text-xs font-bold uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3 text-left">Fecha</th>
                                <th class="px-4 py-3 text-left">Concepto</th>
                                <th class="px-4 py-3 text-left">Socio</th>
                                <th class="px-4 py-3 text-left">Método</th>
                                <th class="px-4 py-3 text-left">Cobrado por</th>
                                <th class="px-4 py-3 text-right">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sky-100 text-blue-950">
                            @forelse($cobros as $cobro)
                                <tr>
                                    <td data-label="Fecha" class="px-4 py-3 whitespace-nowrap font-bold">{{ $cobro->fecha->format('d/m/Y') }}</td>
                                    <td data-label="Concepto" class="px-4 py-3">{{ $cobro->concepto }}</td>
                                    <td data-label="Socio" class="px-4 py-3 font-bold">{{ $cobro->socio }}</td>
                                    <td data-label="Método" class="px-4 py-3 whitespace-nowrap">
                                        @if($cobro->metodo)
                                            {{ $cobro->metodo->icono() }} {{ $cobro->metodo->etiqueta() }}
                                        @else
                                            <span class="text-amber-700 font-bold">Sin especificar</span>
                                        @endif
                                    </td>
                                    <td data-label="Cobrado por" class="px-4 py-3 text-xs font-bold text-sky-800">{{ $cobro->cobrador }}</td>
                                    <td data-label="Monto" class="px-4 py-3 text-right whitespace-nowrap font-extrabold text-emerald-700">{{ $bs($cobro->monto) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center font-bold text-sky-700">No se registraron cobros en {{ $periodo }}.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="hidden print:block text-xs text-gray-500">Generado el {{ now()->format('d/m/Y H:i') }} por {{ auth()->user()->name }}.</p>
        </div>
    </div>
</x-app-layout>
