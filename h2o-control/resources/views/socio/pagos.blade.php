@php
    use App\Enums\MetodoPago;
    use App\Support\Meses;

    $bs = fn ($monto) => 'Bs. '.number_format($monto, 2, ',', '.');
    $input = 'w-full px-4 py-2.5 bg-white rounded-xl border border-sky-300 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500/50 text-sm font-semibold text-blue-950 shadow-sm cursor-pointer';
    $label = 'block text-xs font-black uppercase text-blue-950 tracking-wider mb-1.5 ml-1';
    $periodo = $filtros['gestion']
        ? ($filtros['mes'] ? Meses::nombre($filtros['mes']).' '.$filtros['gestion'] : 'la gestión '.$filtros['gestion'])
        : 'todo tu historial';
@endphp

<x-app-layout>
    <div class="py-10 bg-sky-200/70 min-h-screen">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Encabezado -->
            <div class="md:flex md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="font-extrabold text-3xl text-blue-950 leading-tight">🧾 Mis Pagos</h2>
                    <p class="text-sm text-sky-900 mt-1 font-bold">Pagos de agua y multas confirmados por Hacienda, con su método de pago</p>
                </div>
                <x-boton-excel :href="route('socio.pagos.exportar', request()->query())" class="mt-4 md:mt-0" />
            </div>

            <!-- Filtros -->
            <form method="GET" action="{{ route('socio.pagos') }}" class="bg-white p-5 rounded-2xl shadow-md border border-sky-100">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label for="gestion" class="{{ $label }}">Año</label>
                        <select name="gestion" id="gestion" onchange="this.form.submit()" class="{{ $input }}">
                            <option value="todas" {{ $filtros['gestion'] === null ? 'selected' : '' }}>Todos los años</option>
                            @foreach($gestiones as $anio)
                                <option value="{{ $anio }}" {{ $filtros['gestion'] === $anio ? 'selected' : '' }}>{{ $anio }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="mes" class="{{ $label }}">Mes</label>
                        <select name="mes" id="mes" onchange="this.form.submit()" class="{{ $input }}" @disabled($filtros['gestion'] === null)>
                            <option value="">Todos los meses</option>
                            @foreach(Meses::NOMBRES as $num => $nombre)
                                <option value="{{ $num }}" {{ $filtros['mes'] === $num ? 'selected' : '' }}>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="metodo" class="{{ $label }}">Método</label>
                        <select name="metodo" id="metodo" onchange="this.form.submit()" class="{{ $input }}">
                            <option value="">QR y efectivo</option>
                            @foreach(MetodoPago::cases() as $metodo)
                                <option value="{{ $metodo->value }}" {{ $filtros['metodo'] === $metodo->value ? 'selected' : '' }}>{{ $metodo->icono() }} {{ $metodo->etiqueta() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="tipo" class="{{ $label }}">Concepto</label>
                        <select name="tipo" id="tipo" onchange="this.form.submit()" class="{{ $input }}">
                            <option value="">Agua y multas</option>
                            <option value="agua" {{ $filtros['tipo'] === 'agua' ? 'selected' : '' }}>💧 Agua</option>
                            <option value="multa" {{ $filtros['tipo'] === 'multa' ? 'selected' : '' }}>⚖️ Multas</option>
                        </select>
                    </div>
                </div>
                <noscript><button type="submit" class="mt-4 bg-blue-700 text-white px-5 py-2.5 rounded-xl font-extrabold text-sm">Filtrar</button></noscript>
            </form>

            <!-- Totales del periodo -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl p-5 shadow-md border-l-8 border-blue-700">
                    <p class="text-xs font-black uppercase tracking-wider text-sky-700">Total pagado</p>
                    <p class="mt-1 text-3xl font-black text-blue-950">{{ $bs($totales['total']) }}</p>
                    <p class="text-xs font-bold text-gray-500">{{ $totales['cantidad'] }} pago(s) en {{ $periodo }}</p>
                </div>
                <div class="bg-white rounded-2xl p-5 shadow-md border-l-8 border-sky-500">
                    <p class="text-xs font-black uppercase tracking-wider text-sky-700">📱 Por QR</p>
                    <p class="mt-1 text-3xl font-black text-sky-700">{{ $bs($totales['qr']) }}</p>
                </div>
                <div class="bg-white rounded-2xl p-5 shadow-md border-l-8 border-emerald-500">
                    <p class="text-xs font-black uppercase tracking-wider text-sky-700">💵 En efectivo</p>
                    <p class="mt-1 text-3xl font-black text-emerald-700">{{ $bs($totales['efectivo']) }}</p>
                </div>
            </div>

            <!-- Lista de pagos -->
            <div class="bg-white rounded-2xl shadow-xl shadow-sky-900/10 border border-sky-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="tabla-responsiva min-w-full divide-y divide-sky-100 text-sm">
                        <thead class="bg-blue-900 text-white text-xs font-bold uppercase tracking-wider">
                            <tr>
                                <th class="px-5 py-4 text-left">Fecha de pago</th>
                                <th class="px-5 py-4 text-left">Concepto</th>
                                <th class="px-5 py-4 text-left">Método</th>
                                <th class="px-5 py-4 text-right">Monto</th>
                                <th class="px-5 py-4 text-left">Registrado por</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sky-100 text-blue-950">
                            @forelse($pagos as $pago)
                                <tr class="hover:bg-sky-50">
                                    <td data-label="Fecha de pago" class="px-5 py-4 whitespace-nowrap font-extrabold">
                                        {{ $pago->fecha->format('d/m/Y') }}
                                        @if($pago->con_hora)
                                            <span class="text-sky-700 font-semibold">{{ $pago->fecha->format('H:i') }}</span>
                                        @endif
                                    </td>
                                    <td data-label="Concepto" class="px-5 py-4 font-bold">{{ $pago->concepto }}</td>
                                    <td data-label="Método" class="px-5 py-4 whitespace-nowrap">
                                        @if($pago->metodo)
                                            <span class="px-2.5 py-1 inline-flex items-center gap-1 rounded-full text-xs font-extrabold border {{ $pago->metodo === MetodoPago::Qr ? 'bg-sky-100 text-sky-800 border-sky-200' : 'bg-emerald-100 text-emerald-800 border-emerald-200' }}">
                                                {{ $pago->metodo->icono() }} {{ $pago->metodo->etiqueta() }}
                                            </span>
                                        @else
                                            <span class="text-xs font-bold text-gray-500" title="Pago registrado antes de que el sistema guardara el método">Sin especificar</span>
                                        @endif
                                    </td>
                                    <td data-label="Monto" class="px-5 py-4 text-right whitespace-nowrap font-black text-emerald-700">{{ $bs($pago->monto) }}</td>
                                    <td data-label="Registrado por" class="px-5 py-4 text-xs font-bold text-sky-800">{{ $pago->registrado_por }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center font-bold text-sky-700">
                                        No tienes pagos registrados en {{ $periodo }} con estos filtros.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div>{{ $pagos->links() }}</div>

            <p class="text-xs font-bold text-sky-900">
                ℹ️ Si pagaste por QR y tu pago aún no aparece aquí, envía tu comprobante al Secretario de Finanzas
                (<a href="{{ \App\Support\WhatsApp::enlace(config('otb.telefono_finanzas')) }}" target="_blank" rel="noopener" class="underline text-blue-700">{{ config('otb.telefono_finanzas') }}</a>)
                para que lo confirme.
            </p>
        </div>
    </div>
</x-app-layout>
