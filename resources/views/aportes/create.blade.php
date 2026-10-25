@php $campo = 'w-full bg-sky-50 border border-sky-200 rounded-xl p-3 font-bold text-sm text-blue-950 focus:outline-none focus:ring-2 focus:ring-blue-500'; @endphp

<x-app-layout>
    <div class="py-12 bg-sky-200/70 min-h-screen">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-xl border border-sky-100">
                <h2 class="text-2xl font-black text-blue-950">🎉 Nuevo aporte</h2>
                <p class="text-sm font-bold text-sky-800 mt-1">
                    Para una actividad (aniversario, mantenimiento, compra de material...). Se le anota la cuota a cada uno de los
                    <span class="font-black">{{ $totalSocios }} socios</span> y queda pendiente hasta que la pague.
                </p>

                <x-mensajes-sesion class="mt-4" />

                @if ($errors->any())
                    <div class="mt-4 p-4 bg-red-100 border-l-4 border-red-500 rounded-r-xl">
                        <p class="font-bold text-red-800 text-sm">⚠️ Corrija los siguientes errores:</p>
                        <ul class="mt-2 list-disc list-inside text-xs font-semibold text-red-700">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('aportes.store') }}" method="POST" class="space-y-5 mt-6"
                      onsubmit="return confirm('¿Registrar este aporte? Se le anotará la cuota a los {{ $totalSocios }} socios.')">
                    @csrf

                    <div>
                        <label for="titulo" class="block text-sm font-bold text-blue-950 mb-1">Actividad</label>
                        <input type="text" name="titulo" id="titulo" maxlength="120" required value="{{ old('titulo') }}"
                               placeholder="Ej.: Aniversario de la OTB 2026" class="{{ $campo }}">
                    </div>

                    <div>
                        <label for="descripcion" class="block text-sm font-bold text-blue-950 mb-1">Descripción <span class="text-sky-700 font-semibold">(opcional)</span></label>
                        <textarea name="descripcion" id="descripcion" rows="3" maxlength="1000"
                                  placeholder="Para qué es el aporte: compra de refrigerios y banda para el aniversario..."
                                  class="{{ $campo }} font-semibold">{{ old('descripcion') }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="monto" class="block text-sm font-bold text-blue-950 mb-1">Monto por socio (Bs.)</label>
                            <input type="number" name="monto" id="monto" step="0.01" min="0.5" max="100000" required value="{{ old('monto') }}"
                                   placeholder="20.00" class="{{ $campo }}">
                            <p id="total-esperado" class="text-xs font-bold text-sky-800 mt-1"></p>
                        </div>
                        <div>
                            <label for="fecha_limite" class="block text-sm font-bold text-blue-950 mb-1">Fecha límite de pago <span class="text-sky-700 font-semibold">(opcional)</span></label>
                            <input type="date" name="fecha_limite" id="fecha_limite" min="{{ now()->toDateString() }}" value="{{ old('fecha_limite') }}" class="{{ $campo }}">
                        </div>
                    </div>

                    <label class="flex items-start gap-3 p-4 bg-sky-50 border border-sky-200 rounded-xl cursor-pointer">
                        <input type="checkbox" name="notificar" value="1" @checked(old('notificar', true)) class="mt-0.5 rounded border-sky-300 text-blue-700 focus:ring-blue-500">
                        <span class="text-sm font-bold text-blue-950">
                            📢 Enviar una notificación a todos los socios
                            <span class="block text-xs font-semibold text-sky-800 mt-0.5">Con la descripción, el monto, la fecha límite y cómo pagar (QR o efectivo). Les llega también al celular si activaron los avisos.</span>
                        </span>
                    </label>

                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2">
                        <a href="{{ route('multas.index') }}" class="text-center bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-5 py-3 rounded-xl text-sm">Cancelar</a>
                        <button type="submit" class="bg-blue-700 hover:bg-blue-800 text-white font-extrabold px-6 py-3 rounded-xl text-sm shadow-md">
                            Registrar aporte
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Cuánto se juntaría si pagan todos
        (function () {
            const monto = document.getElementById('monto');
            const total = document.getElementById('total-esperado');
            const socios = @json($totalSocios);
            const mostrar = () => {
                const valor = parseFloat(monto.value);
                total.textContent = valor > 0
                    ? 'Si pagan todos: Bs. ' + (valor * socios).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    : '';
            };
            monto.addEventListener('input', mostrar);
            mostrar();
        })();
    </script>
</x-app-layout>
