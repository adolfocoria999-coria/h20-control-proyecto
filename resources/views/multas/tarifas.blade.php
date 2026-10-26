@php $campo = 'bg-sky-50 border border-sky-200 rounded-xl p-2.5 font-bold text-sm text-blue-950 focus:ring-2 focus:ring-blue-500'; @endphp

<x-app-layout>
    <div class="py-12 bg-sky-200/70 min-h-screen">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div>
                <a href="{{ route('multas.index') }}" class="text-sm font-extrabold text-blue-800 hover:underline">← Volver a Multas y Aportes</a>
            </div>

            <x-mensajes-sesion />

            <div class="bg-white p-6 rounded-2xl shadow-xl">
                <h3 class="text-xl font-black text-blue-950">Configuración de Tarifas de Multas</h3>
                <p class="text-xs font-bold text-sky-800 mt-1 mb-4">Cada tipo de multa con su monto. Al registrar una multa se completa el monto solo (se puede cambiar).</p>

                @if ($errors->any())
                    <div class="mb-4 p-3 bg-red-50 border-l-4 border-red-500 rounded-r-xl text-xs font-bold text-red-800">
                        @foreach ($errors->all() as $error)
                            <p>⚠️ {{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form action="{{ route('tarifas-multas.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    @csrf
                    <input type="text" name="nombre" maxlength="255" placeholder="Nombre (Ej: Reunión)" class="{{ $campo }}" required>
                    <input type="number" step="0.01" min="0" name="monto_predeterminado" placeholder="Monto (Bs.)" class="{{ $campo }}" required>
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white rounded-xl font-extrabold text-sm px-4 py-2.5">Guardar Tarifa</button>
                </form>

                <div class="divide-y divide-sky-100">
                    @forelse($tarifas as $t)
                        <div x-data="{ editando: false }" class="py-3">
                            <!-- Vista -->
                            <div x-show="!editando" class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2">
                                <div>
                                    <h4 class="font-bold text-blue-950">{{ $t->nombre }}</h4>
                                    <p class="text-xs text-sky-800">Monto predeterminado: Bs. {{ number_format($t->monto_predeterminado, 2) }}</p>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" @click="editando = true"
                                            class="bg-white border border-sky-300 hover:bg-sky-50 text-blue-900 font-bold px-3 py-1.5 rounded-lg text-xs shadow">✏️ Editar</button>
                                    <form action="{{ route('tarifas-multas.destroy', $t) }}" method="POST"
                                          onsubmit="return confirm('¿Eliminar la tarifa «{{ $t->nombre }}»? Las multas ya registradas con ella no cambian.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold px-3 py-1.5 rounded-lg text-xs shadow">🗑️ Eliminar</button>
                                    </form>
                                </div>
                            </div>

                            <!-- Edición en el lugar -->
                            <form x-show="editando" x-cloak action="{{ route('tarifas-multas.update', $t) }}" method="POST"
                                  class="grid grid-cols-1 md:grid-cols-4 gap-3 items-center">
                                @csrf
                                @method('PUT')
                                <input type="text" name="nombre" maxlength="255" value="{{ $t->nombre }}" class="{{ $campo }} md:col-span-2" required aria-label="Nombre de la tarifa">
                                <input type="number" step="0.01" min="0" name="monto_predeterminado" value="{{ number_format((float) $t->monto_predeterminado, 2, '.', '') }}" class="{{ $campo }}" required aria-label="Monto en Bs.">
                                <div class="flex gap-2">
                                    <button type="submit" class="flex-1 bg-blue-700 hover:bg-blue-800 text-white font-extrabold px-3 py-2.5 rounded-xl text-xs">Guardar</button>
                                    <button type="button" @click="editando = false" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-3 py-2.5 rounded-xl text-xs">Cancelar</button>
                                </div>
                            </form>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm font-bold text-sky-700">Todavía no hay tarifas de multas.</p>
                    @endforelse
                </div>

                <p class="mt-4 text-xs font-bold text-sky-800">ℹ️ Cambiar o eliminar una tarifa no modifica las multas ya registradas: conservan su nombre y monto.</p>
            </div>
        </div>
    </div>
</x-app-layout>
