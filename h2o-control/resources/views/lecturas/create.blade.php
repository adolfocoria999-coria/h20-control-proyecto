<x-app-layout>
    <div class="py-12 bg-sky-100 min-h-screen">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl rounded-2xl p-6 border border-sky-100">
                
                <div class="border-b border-gray-100 pb-4 mb-6">
                    <h2 class="font-extrabold text-2xl text-blue-950">
                        {{ __('Registrar Nueva Lectura de Medidor') }}
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">Ingrese los datos de consumo correspondientes al periodo actual.</p>
                </div>

                <form action="{{ route('lecturas.store') }}" method="POST" id="form-lectura">
                    @csrf
                    <x-errores-formulario />

                    <div class="mb-5">
                        <label for="buscar_socio" class="block text-sm font-bold text-blue-950 mb-2">Seleccionar Socio / Vecino:</label>
                        
                        <div class="relative mb-2">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">🔍</span>
                            <input type="text" id="buscar_socio" placeholder="Escribe el nombre o C.I. del socio para filtrar..." 
                                   class="w-full pl-9 pr-4 py-2.5 border border-gray-300 rounded-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-sm bg-gray-50 placeholder-gray-400 font-semibold text-blue-950">
                        </div>

                        <div class="relative">
                            <select name="user_id" id="user_id" required 
                                    class="w-full bg-white border border-gray-300 rounded-xl shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200 p-3 text-sm font-medium text-gray-800 appearance-none cursor-pointer">
                                <option value="">-- Selecciona un socio --</option>
                                @foreach($socios as $socio)
                                    <option value="{{ $socio->id }}" class="opcion-socio py-2">
                                        👤 {{ $socio->name }} (C.I. {{ $socio->ci ?? 'S/N' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <p id="sin-resultados" class="text-sm text-red-600 font-bold mt-2 hidden">❌ No se encontraron socios con ese criterio.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                        <div>
                            <label for="mes" class="block text-sm font-bold text-blue-950 mb-2">Mes de Lectura:</label>
                            <select name="mes" id="mes" required class="w-full border border-gray-300 rounded-xl p-3 text-sm font-medium text-gray-800 bg-white">
                                @foreach(\App\Support\Meses::NOMBRES as $num => $nombre)
                                    <option value="{{ $num }}" {{ old('mes', date('n')) == $num ? 'selected' : '' }}>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="gestion" class="block text-sm font-bold text-blue-950 mb-2">Gestión (Año):</label>
                            <input type="number" name="gestion" id="gestion" value="{{ date('Y') }}" required 
                                   class="w-full border border-gray-300 rounded-xl p-3 text-sm font-semibold text-gray-800 bg-white">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="lectura_anterior" class="block text-sm font-bold text-blue-950 mb-2">Lectura Anterior (m³):</label>
                            <input type="number" step="any" name="lectura_anterior" id="lectura_anterior" placeholder="0.0" required 
                                   class="w-full border border-gray-300 rounded-xl p-3 text-sm font-black text-blue-950 bg-white focus:ring-2 focus:ring-blue-200 transition-colors">
                        </div>

                        <div>
                            <label for="lectura_actual" class="block text-sm font-bold text-blue-950 mb-2">Lectura Actual (m³):</label>
                            <input type="number" step="any" name="lectura_actual" id="lectura_actual" placeholder="0.0" required 
                                   class="w-full border border-gray-300 rounded-xl p-3 text-sm font-black text-blue-950 bg-white focus:ring-2 focus:ring-blue-200">
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 border-t border-gray-100 pt-4 mt-6">
                        <a href="{{ route('lecturas.index') }}" 
                           class="px-5 py-2.5 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-bold text-sm shadow-md transition-all text-center">
                            Cancelar
                        </a>
                        <button type="submit" 
                                class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-sm shadow-md shadow-emerald-200 transition-all">
                            Guardar Registro
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <script>
        const inputBuscar = document.getElementById('buscar_socio');
        const selectSocio = document.getElementById('user_id');
        const inputAnterior = document.getElementById('lectura_anterior');
        const mensajeSinResultados = document.getElementById('sin-resultados');
        const opciones = selectSocio.getElementsByClassName('opcion-socio');

        // 1. Filtrado dinámico + Obtención automática de lectura previa
        inputBuscar.addEventListener('input', function() {
            const termino = this.value.toLowerCase().trim();
            let coincidencias = 0;
            let primerIdMatch = null;

            if (termino === "") {
                for (let i = 0; i < opciones.length; i++) {
                    opciones[i].disabled = false;
                    opciones[i].style.display = "";
                }
                selectSocio.value = "";
                mensajeSinResultados.classList.add('hidden');
                resetearCampoAnterior();
                return;
            }

            for (let i = 0; i < opciones.length; i++) {
                const textoOpcion = opciones[i].text.toLowerCase();
                
                if (textoOpcion.includes(termino)) {
                    opciones[i].disabled = false;
                    opciones[i].style.display = "";
                    coincidencias++;
                    
                    if (!primerIdMatch) {
                        primerIdMatch = opciones[i].value;
                    }
                } else {
                    opciones[i].disabled = true;
                    opciones[i].style.display = "none";
                }
            }

            if (primerIdMatch) {
                selectSocio.value = primerIdMatch;
                mensajeSinResultados.classList.add('hidden');
                obtenerLecturaPrevia(primerIdMatch); // <-- Consulta AJAX agregada aquí
            } else {
                selectSocio.value = "";
                mensajeSinResultados.classList.remove('hidden');
                resetearCampoAnterior();
            }
        });

        // 2. Evento por si el usuario cambia el socio directo en el Dropdown
        selectSocio.addEventListener('change', function() {
            if (this.value) {
                obtenerLecturaPrevia(this.value);
            } else {
                resetearCampoAnterior();
            }
        });

        // 3. Función AJAX que consulta el servidor
        function obtenerLecturaPrevia(usuarioId) {
            fetch(`/lecturas/ultima/${usuarioId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.tiene_lectura) {
                        // Socio antiguo -> Carga valor y bloquea campo
                        inputAnterior.value = data.lectura_actual;
                        inputAnterior.setAttribute('readonly', 'readonly');
                        inputAnterior.classList.add('bg-gray-100', 'cursor-not-allowed', 'text-gray-500');
                        inputAnterior.classList.remove('bg-white');
                    } else {
                        // Socio nuevo (1ra lectura) -> Habilita edición manual
                        resetearCampoAnterior();
                    }
                })
                .catch(error => console.error('Error al obtener la lectura previa:', error));
        }

        function resetearCampoAnterior() {
            inputAnterior.value = '';
            inputAnterior.removeAttribute('readonly');
            inputAnterior.classList.remove('bg-gray-100', 'cursor-not-allowed', 'text-gray-500');
            inputAnterior.classList.add('bg-white');
        }
    </script>
</x-app-layout>