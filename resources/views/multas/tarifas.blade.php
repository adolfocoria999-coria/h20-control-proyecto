<x-app-layout>
    <div class="py-12 bg-sky-200/70 min-h-screen">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white p-6 rounded-2xl shadow-xl">
                <h3 class="text-xl font-black text-blue-950 mb-4">Configuración de Tarifas de Multas</h3>
                
                <form action="{{ route('tarifas-multas.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    @csrf
                    <input type="text" name="nombre" placeholder="Nombre (Ej: Reunión)" class="bg-sky-50 border rounded-xl p-2.5 font-bold text-sm" required>
                    <input type="number" step="0.01" name="monto_predeterminado" placeholder="Monto (Bs.)" class="bg-sky-50 border rounded-xl p-2.5 font-bold text-sm" required>
                    <button type="submit" class="bg-green-600 text-white rounded-xl font-extrabold text-sm px-4 py-2.5">Guardar Tarifa</button>
                </form>

                <div class="divide-y divide-sky-100">
                    @foreach($tarifas as $t)
                    <div class="py-3 flex justify-between items-center">
                        <div>
                            <h4 class="font-bold text-blue-950">{{ $t->nombre }}</h4>
                            <p class="text-xs text-sky-800">Monto predeterminado: Bs. {{ number_format($t->monto_predeterminado, 2) }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>