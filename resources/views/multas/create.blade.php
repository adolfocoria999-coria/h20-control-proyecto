<x-app-layout>
    <div class="py-12 bg-sky-200/70 min-h-screen">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white p-8 rounded-2xl shadow-xl border border-sky-100">
                <h2 class="text-2xl font-black text-blue-950 mb-6">Registrar Nueva Multa / Sanción</h2>

                <!-- Bloque de errores de validación -->
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

                <form action="{{ route('multas.store') }}" method="POST" class="space-y-6">
                    @csrf

                    <!-- Campo Oculto para capturar el texto visible del select -->
                    <input type="hidden" name="tipo_multa_texto" id="tipo_multa_texto">

                    <!-- Socio Individual con Autocompletado Instantáneo -->
                    <div id="campo_individual" class="space-y-2">
                        <label class="block text-sm font-bold text-blue-950">Seleccionar Socio:</label>
                        <input type="text" id="buscar_socio_input" list="lista_socios_datalist" oninput="seleccionarSocio()" placeholder="🔍 Escriba un nombre o correo para filtrar..." class="w-full bg-sky-50 border border-sky-200 rounded-xl p-3 font-bold text-sm text-blue-950 focus:outline-none focus:ring-2 focus:ring-blue-500" autocomplete="off" required>
                        
                        <datalist id="lista_socios_datalist">
                            @foreach($socios as $s)
                                <option data-id="{{ $s->id }}" value="{{ $s->name }} ({{ $s->email }})"></option>
                            @endforeach
                        </datalist>

                        <input type="hidden" name="user_id" id="user_id_hidden">
                    </div>

                    <!-- Socios Masivo con Buscador -->
                    <div id="campo_masivo" class="hidden space-y-2">
                        <label class="block text-sm font-bold text-blue-950">Seleccionar Inasistentes:</label>
                        <input type="text" id="buscar_socio_masivo" onkeyup="filtrarSociosMasivo()" placeholder="🔍 Filtrar lista de socios..." class="w-full bg-white border border-sky-200 rounded-lg p-2 text-xs font-bold text-blue-950 focus:ring-2 focus:ring-blue-500">
                        
                        <div class="max-h-48 overflow-y-auto bg-sky-50 p-4 rounded-xl border border-sky-200 space-y-2" id="lista_masiva">
                            @foreach($socios as $s)
                                <label class="item-socio flex items-center space-x-2 text-sm font-bold text-blue-950">
                                    <input type="checkbox" name="socios[]" value="{{ $s->id }}" class="rounded text-blue-600">
                                    <span class="nombre-socio">{{ $s->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Tipo de Infracción -->
                    <div>
                        <label class="block text-sm font-bold text-blue-950 mb-2">Tipo de Infracción:</label>
                        <select name="tarifa_multa_id" id="tarifa_multa_id" onchange="actualizarMonto()" class="w-full bg-sky-50 border border-sky-200 rounded-xl p-3 font-bold text-sm text-blue-950 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="" disabled selected>-- Seleccione una Infracción --</option>
                            
                            @foreach($tarifas as $t)
                                <option value="{{ $t->id }}" data-monto="{{ $t->monto_predeterminado }}" {{ old('tarifa_multa_id') == $t->id ? 'selected' : '' }}>
                                    {{ $t->nombre }} - (Bs. {{ number_format($t->monto_predeterminado, 2) }})
                                </option>
                            @endforeach

                            <option value="otro" data-monto="0" {{ old('tarifa_multa_id') === 'otro' ? 'selected' : '' }}>📌 Otro (Detallar motivo obligatoriamente)</option>
                        </select>
                    </div>

                    <!-- Monto y Fecha -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-blue-950 mb-2">Monto (Bs.):</label>
                            <input type="number" step="0.01" min="0" name="monto" id="monto" class="w-full bg-sky-50 border border-sky-200 rounded-xl p-3 font-extrabold text-sm text-emerald-600 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-blue-950 mb-2">Fecha del Evento:</label>
                            <input type="date" name="fecha_multa" value="{{ date('Y-m-d') }}" class="w-full bg-sky-50 border border-sky-200 rounded-xl p-3 font-bold text-sm text-blue-950 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                    </div>

                    <!-- Detalle / Motivo -->
                    <div>
                        <label class="block text-sm font-bold text-blue-950 mb-2">Detalle / Motivo:</label>
                        <input type="text" name="motivo" id="motivo" placeholder="Ej: Inasistencia a Asamblea Ordinaria de Julio" class="w-full bg-sky-50 border border-sky-200 rounded-xl p-3 font-semibold text-sm text-blue-950 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <!-- Botones -->
                    <div class="flex justify-end gap-3 pt-4 border-t border-sky-100">
                        <a href="{{ route('multas.index') }}" class="bg-gray-300 hover:bg-gray-400 px-5 py-3 rounded-xl font-bold text-sm text-gray-800 transition-all">Cancelar</a>
                        <button type="submit" class="bg-blue-700 hover:bg-blue-800 text-white px-6 py-3 rounded-xl font-extrabold text-sm shadow-md transition-all">Guardar Multa</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function seleccionarSocio() {
            const input = document.getElementById('buscar_socio_input');
            const hidden = document.getElementById('user_id_hidden');
            const datalist = document.getElementById('lista_socios_datalist');

            hidden.value = "";

            for (let option of datalist.options) {
                if (option.value === input.value) {
                    hidden.value = option.getAttribute('data-id');
                    break;
                }
            }
        }

        function toggleModo(modo) {
            document.getElementById('campo_individual').classList.toggle('hidden', modo !== 'individual');
            document.getElementById('campo_masivo').classList.toggle('hidden', modo !== 'masivo');
        }

        function actualizarMonto() {
            const select = document.getElementById('tarifa_multa_id');
            const selectedOption = select.options[select.selectedIndex];
            const monto = selectedOption.getAttribute('data-monto');
            const txtMotivo = document.getElementById('motivo');
            const txtTipoTexto = document.getElementById('tipo_multa_texto');
            
            // Guarda el texto visible de la opción (limpiando sufijos de monto si existen)
            txtTipoTexto.value = select.value === 'otro' ? '' : selectedOption.text.split(' - (Bs.')[0].trim();

            if (monto !== null) {
                document.getElementById('monto').value = monto;
            }

            if (select.value === 'otro') {
                txtMotivo.required = true;
                txtMotivo.placeholder = "⚠️ ESPECIFIQUE EL MOTIVO Y DETALLE DE LA MULTA (Obligatorio)";
                txtMotivo.focus();
            } else {
                txtMotivo.required = false;
                txtMotivo.placeholder = "Ej: Inasistencia a Asamblea Ordinaria de Julio";
            }
        }

        function filtrarSociosMasivo() {
            const filtro = document.getElementById('buscar_socio_masivo').value.toLowerCase();
            const items = document.querySelectorAll('#lista_masiva .item-socio');
            
            items.forEach(item => {
                const texto = item.textContent.toLowerCase();
                item.style.display = texto.includes(filtro) ? 'flex' : 'none';
            });
        }
    </script>
</x-app-layout>