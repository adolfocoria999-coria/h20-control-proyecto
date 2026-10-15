@php $bs = fn ($monto) => 'Bs. '.number_format((float) $monto, 2, ',', '.'); @endphp

<x-app-layout>
    <div class="py-10 bg-sky-200/70 min-h-screen">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div>
                <h2 class="font-extrabold text-3xl text-blue-950 leading-tight">Tarifa del Agua</h2>
                <p class="text-sm text-sky-900 mt-1 font-bold">Precio por cada m³ consumido. Solo el superadministrador puede cambiarla.</p>
            </div>

            <x-mensajes-sesion />

            <div class="bg-white p-6 rounded-2xl shadow-xl border border-sky-100 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <p class="text-sm font-bold text-sky-600 uppercase tracking-wider">Tarifa vigente</p>
                    <p class="text-4xl font-black text-blue-950 mt-1">{{ $bs($tarifa) }} <span class="text-lg text-sky-700">/ m³</span></p>
                    <p class="text-xs font-bold text-sky-800 mt-2">Ej.: un consumo de 10 m³ cuesta {{ $bs($tarifa * 10) }}.</p>
                </div>

                <form method="POST" action="{{ route('tarifa-agua.update') }}"
                      onsubmit="return confirm('¿Cambiar la tarifa del agua? Se aplicará a las lecturas que se registren desde ahora.')">
                    @csrf
                    @method('PUT')
                    <label for="tarifa_m3" class="block text-sm font-extrabold text-blue-950 mb-2">Nueva tarifa (Bs. por m³)</label>
                    <input type="number" step="0.01" min="0.1" max="1000" name="tarifa_m3" id="tarifa_m3" required
                           value="{{ old('tarifa_m3', number_format($tarifa, 2, '.', '')) }}"
                           class="w-full rounded-xl border border-sky-200 px-4 py-3 focus:ring-2 focus:ring-blue-500 font-bold text-blue-900 bg-sky-50">
                    <x-input-error :messages="$errors->get('tarifa_m3')" class="mt-1 text-xs" />
                    <button type="submit" class="mt-3 w-full bg-blue-700 hover:bg-blue-800 text-white px-5 py-3 rounded-xl font-extrabold text-sm shadow-md">
                        Guardar nueva tarifa
                    </button>
                </form>
            </div>

            <div class="p-4 bg-sky-50 border-l-4 border-sky-500 rounded-r-xl text-xs font-bold text-sky-900 space-y-1">
                <p>ℹ️ La nueva tarifa se aplica a las <span class="font-black">lecturas que se registren desde ahora</span>.</p>
                <p>Las lecturas ya registradas conservan la tarifa con la que se calcularon, estén pagadas o pendientes: a nadie le cambia una deuda ya emitida.</p>
                @if($pendientesConOtraTarifa > 0)
                    <p>Hay {{ $pendientesConOtraTarifa }} lectura(s) pendiente(s) de cobro con una tarifa anterior.</p>
                @endif
            </div>

            <div class="bg-white rounded-2xl shadow-xl border border-sky-100 overflow-hidden">
                <h3 class="px-6 py-4 text-lg font-black text-blue-950 border-b border-sky-100">Historial de cambios</h3>
                <table class="tabla-responsiva min-w-full divide-y divide-sky-100 text-sm">
                    <thead class="bg-blue-900 text-white text-xs font-bold uppercase tracking-wider">
                        <tr>
                            <th class="px-5 py-3 text-left">Fecha</th>
                            <th class="px-5 py-3 text-left">Cambio</th>
                            <th class="px-5 py-3 text-left">Quién</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sky-100 text-blue-950">
                        @forelse($cambios as $cambio)
                            <tr>
                                <td data-label="Fecha" class="px-5 py-3 whitespace-nowrap font-bold">{{ $cambio->created_at->format('d/m/Y H:i') }}</td>
                                <td data-label="Cambio" class="px-5 py-3">
                                    {{ $bs($cambio->cambios['tarifa_m3']['antes'] ?? 0) }} → <span class="font-black">{{ $bs($cambio->cambios['tarifa_m3']['despues'] ?? 0) }}</span>
                                </td>
                                <td data-label="Quién" class="px-5 py-3 text-xs font-bold text-sky-800">{{ $cambio->usuario_nombre }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-8 text-center font-bold text-sky-700">La tarifa no se cambió desde que se usa el sistema.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
