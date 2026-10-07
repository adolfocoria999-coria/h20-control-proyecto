<x-app-layout>
    <div class="py-12 bg-sky-200/70 min-h-screen">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="mb-6">
                <h2 class="font-extrabold text-3xl text-blue-950 leading-tight">Editar Balance</h2>
                <p class="text-sm text-sky-900 mt-1 font-bold">Modificando el registro de {{ $balance->mes_nombre }} {{ $balance->gestion }}</p>
            </div>

            <div class="bg-white rounded-2xl shadow-xl border border-sky-100 p-8">
                <!-- 🟢 Se agregó enctype="multipart/form-data" -->
                <form action="{{ route('finanzas.update', $balance->id) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    <x-errores-formulario />
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Mes -->
                        <div>
                            <label class="block text-sm font-extrabold text-blue-950 mb-2">Mes</label>
                            <select name="mes" required class="w-full rounded-xl border border-sky-200 px-4 py-3 focus:ring-2 focus:ring-blue-500 font-bold text-blue-900 bg-sky-50">
                                @foreach(\App\Support\Meses::NOMBRES as $num => $nombre)
                                    <option value="{{ $num }}" {{ old('mes', $balance->mes) == $num ? 'selected' : '' }}>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Gestión -->
                        <div>
                            <label class="block text-sm font-extrabold text-blue-950 mb-2">Gestión (Año)</label>
                            <input type="number" name="gestion" required value="{{ $balance->gestion }}" 
                                class="w-full rounded-xl border border-sky-200 px-4 py-3 focus:ring-2 focus:ring-blue-500 font-bold text-blue-900 bg-sky-50">
                        </div>

                        <!-- Ingresos -->
                        <div>
                            <label class="block text-sm font-extrabold text-blue-950 mb-2">Ingresos (Bs.) <span class="font-bold text-sky-700">— sin multas</span></label>
                            <input type="number" step="0.01" name="ingresos" required min="0" value="{{ old('ingresos', $balance->ingresos) }}"
                                class="w-full rounded-xl border border-sky-200 px-4 py-3 focus:ring-2 focus:ring-green-500 font-bold text-green-700 bg-sky-50">
                            @if($balance->ingresos_multas > 0)
                                <p class="mt-1.5 text-xs font-bold text-sky-800">➕ Además se suman automáticamente Bs. {{ number_format($balance->ingresos_multas, 2) }} de multas cobradas este mes.</p>
                            @endif
                        </div>

                        <!-- Egresos -->
                        <div>
                            <label class="block text-sm font-extrabold text-blue-950 mb-2">Total Egresos/Gastos (Bs.)</label>
                            <input type="number" step="0.01" name="egresos" required min="0" value="{{ $balance->egresos }}"
                                class="w-full rounded-xl border border-sky-200 px-4 py-3 focus:ring-2 focus:ring-red-500 font-bold text-red-700 bg-sky-50">
                        </div>
                    </div>

                    <!-- Detalle -->
                    <div>
                        <label class="block text-sm font-extrabold text-blue-950 mb-2">Detalle de Movimientos</label>
                        <textarea name="detalle" rows="4" 
                            class="w-full rounded-xl border border-sky-200 px-4 py-3 focus:ring-2 focus:ring-blue-500 font-medium text-blue-900 bg-sky-50">{{ $balance->detalle }}</textarea>
                    </div>

                    <!-- 🟢 NUEVO CAMPO: Subir/Actualizar Comprobante -->
                    <div class="mt-4 border-t border-sky-100 pt-4">
                        <label class="block text-sm font-extrabold text-blue-950 mb-2">Comprobante / Recibo de respaldo (PDF, JPG, PNG)</label>
                        
                        @if($balance->comprobante_url)
                            <div class="mb-3 p-3 bg-emerald-50 rounded-lg border border-emerald-200 flex items-center justify-between">
                                <span class="text-sm font-bold text-emerald-800">✅ Ya existe un comprobante subido.</span>
                                <a href="{{ route('finanzas.comprobante', $balance) }}" target="_blank" class="text-xs font-extrabold text-emerald-700 hover:text-emerald-900 underline">
                                    Ver archivo actual
                                </a>
                            </div>
                        @endif

                        <input type="file" name="comprobante" accept=".pdf, .jpg, .jpeg, .png"
                            class="w-full rounded-xl border border-sky-200 px-4 py-3 bg-sky-50 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-bold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200 transition-all cursor-pointer">
                        <p class="text-xs text-sky-700 mt-1 font-semibold">Si subes un archivo nuevo, reemplazará al anterior. Máximo 5MB.</p>
                    </div>

                    <div class="flex justify-end space-x-4 pt-6">
                        <a href="{{ route('finanzas.index') }}" class="px-6 py-3 bg-gray-200 text-gray-700 font-bold rounded-xl hover:bg-gray-300 transition-colors">Cancelar</a>
                        <button type="submit" class="px-6 py-3 bg-blue-700 text-white font-extrabold rounded-xl shadow-lg shadow-blue-500/30 hover:bg-blue-800 transition-colors transform hover:-translate-y-0.5">
                            Actualizar Balance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>