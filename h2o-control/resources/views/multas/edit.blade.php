<x-app-layout>
    <div class="py-12 bg-sky-200/70 min-h-screen">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white p-8 rounded-2xl shadow-xl border border-sky-100">
                <h2 class="text-2xl font-black text-blue-950 mb-6">Editar Multa / Sanción</h2>

                @if ($errors->any())
                    <div class="mb-6 p-4 bg-red-100 border-l-4 border-red-500 rounded-r-xl">
                        <p class="font-bold text-red-800 text-sm">⚠️ Corrija los siguientes errores:</p>
                        <ul class="mt-2 list-disc list-inside text-xs font-semibold text-red-700">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('multas.update', $multa->id) }}" method="POST" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <input type="hidden" name="tipo_multa_texto" id="tipo_multa_texto" value="{{ $multa->tipo_multa }}">

                    <!-- Socio -->
                    <div>
                        <label class="block text-sm font-bold text-blue-950 mb-2">Socio Afectado:</label>
                        <select name="user_id" class="w-full bg-sky-50 border border-sky-200 rounded-xl p-3 font-bold text-sm text-blue-950 focus:ring-2 focus:ring-blue-500" required>
                            @foreach($socios as $s)
                                <option value="{{ $s->id }}" {{ $multa->user_id == $s->id ? 'selected' : '' }}>
                                    {{ $s->name }} ({{ $s->email }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Tipo Infracción -->
                    <div>
                        <label class="block text-sm font-bold text-blue-950 mb-2">Tipo de Infracción:</label>
                        <select name="tarifa_multa_id" id="tarifa_multa_id" onchange="actualizarMonto()" class="w-full bg-sky-50 border border-sky-200 rounded-xl p-3 font-bold text-sm text-blue-950 focus:ring-2 focus:ring-blue-500" required>
                            @foreach($tarifas as $t)
                                <option value="{{ $t->id }}" data-monto="{{ $t->monto_predeterminado }}" {{ old('tarifa_multa_id', $multa->tarifa_multa_id) == $t->id ? 'selected' : '' }}>
                                    {{ $t->nombre }} - (Bs. {{ number_format($t->monto_predeterminado, 2) }})
                                </option>
                            @endforeach

                            <option value="otro" data-monto="0" {{ is_null($multa->tarifa_multa_id) ? 'selected' : '' }}>📌 Otro (Detallar motivo obligatoriamente)</option>
                        </select>
                    </div>

                    <!-- Monto, Fecha y Estado -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-blue-950 mb-2">Monto (Bs.):</label>
                            <input type="number" step="0.01" min="0" name="monto" id="monto" value="{{ old('monto', $multa->monto) }}" class="w-full bg-sky-50 border border-sky-200 rounded-xl p-3 font-extrabold text-sm text-emerald-600 focus:ring-2 focus:ring-blue-500" required>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-blue-950 mb-2">Fecha del Evento:</label>
                            <input type="date" name="fecha_multa" value="{{ old('fecha_multa', $multa->fecha_multa->format('Y-m-d')) }}" class="w-full bg-sky-50 border border-sky-200 rounded-xl p-3 font-bold text-sm text-blue-950 focus:ring-2 focus:ring-blue-500" required>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-blue-950 mb-2">Estado:</label>
                            <select name="estado" id="estado" onchange="document.getElementById('campo-metodo').classList.toggle('hidden', this.value !== 'pagado')" class="w-full bg-sky-50 border border-sky-200 rounded-xl p-3 font-bold text-sm text-blue-950 focus:ring-2 focus:ring-blue-500" required>
                                <option value="pendiente" {{ $multa->estado === 'pendiente' ? 'selected' : '' }}>⏳ Pendiente</option>
                                <option value="pagado" {{ $multa->estado === 'pagado' ? 'selected' : '' }}>✅ Pagado</option>
                                <option value="condonado" {{ $multa->estado === 'condonado' ? 'selected' : '' }}>🔵 Condonado</option>
                            </select>
                        </div>
                        <!-- Método de pago: obligatorio si la multa está pagada (arqueo) -->
                        <div id="campo-metodo" class="{{ old('estado', $multa->estado) === 'pagado' ? '' : 'hidden' }}">
                            <label class="block text-sm font-bold text-blue-950 mb-2">Método de Pago:</label>
                            <select name="metodo_pago" class="w-full bg-sky-50 border border-sky-200 rounded-xl p-3 font-bold text-sm text-blue-950 focus:ring-2 focus:ring-blue-500">
                                <option value="">-- Seleccione --</option>
                                @foreach(\App\Enums\MetodoPago::cases() as $metodo)
                                    <option value="{{ $metodo->value }}" {{ old('metodo_pago', $multa->metodo_pago?->value) === $metodo->value ? 'selected' : '' }}>{{ $metodo->icono() }} {{ $metodo->etiqueta() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Detalle / Motivo -->
                    <div>
                        <label class="block text-sm font-bold text-blue-950 mb-2">Detalle / Motivo:</label>
                        <input type="text" name="motivo" id="motivo" value="{{ old('motivo', $multa->motivo) }}" class="w-full bg-sky-50 border border-sky-200 rounded-xl p-3 font-semibold text-sm text-blue-950 focus:ring-2 focus:ring-blue-500">
                    </div>

                    <!-- Botones -->
                    <div class="flex justify-end gap-3 pt-4 border-t border-sky-100">
                        <a href="{{ route('multas.index') }}" class="bg-gray-300 hover:bg-gray-400 px-5 py-3 rounded-xl font-bold text-sm text-gray-800 transition-all">Cancelar</a>
                        <button type="submit" class="bg-blue-700 hover:bg-blue-800 text-white px-6 py-3 rounded-xl font-extrabold text-sm shadow-md transition-all">Actualizar Multa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function actualizarMonto() {
            const select = document.getElementById('tarifa_multa_id');
            const selectedOption = select.options[select.selectedIndex];
            const monto = selectedOption.getAttribute('data-monto');
            const txtMotivo = document.getElementById('motivo');
            const txtTipoTexto = document.getElementById('tipo_multa_texto');

            txtTipoTexto.value = select.value === 'otro' ? '' : selectedOption.text.split(' - (Bs.')[0].trim();

            if (monto !== null && select.value !== 'otro') {
                document.getElementById('monto').value = monto;
            }

            if (select.value === 'otro') {
                txtMotivo.required = true;
            } else {
                txtMotivo.required = false;
            }
        }
    </script>
</x-app-layout>