<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Modificar / Corregir Lectura de Agua') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                
                <form action="{{ route('lecturas.update', $lectura->id) }}" method="POST">
                    @csrf
                    <x-mensajes-sesion class="mb-4" />
                    <x-errores-formulario />
                    @method('PUT')

                    <div class="mb-4">
                        <label for="user_id" class="block text-sm font-medium text-gray-700 mb-2">Socio / Vecino</label>
                        
                        <div class="relative mb-2">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">🔍</span>
                            <input type="text" id="buscar_socio" placeholder="Escribe el nombre o C.I. del socio para filtrar..." 
                                   class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm bg-gray-50 placeholder-gray-400">
                        </div>

                        <select name="user_id" id="user_id" class="w-full border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;">
                            @foreach($socios as $socio)
                                <option value="{{ $socio->id }}" {{ $lectura->user_id == $socio->id ? 'selected' : '' }}>
                                    {{ $socio->name }} (C.I. {{ $socio->ci ?? 'S/N' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div style="display: flex; gap: 15px;" class="mb-4">
                        <div style="flex: 1;">
                            <label for="mes" class="block text-sm font-medium text-gray-700 mb-2">Mes</label>
                            <select name="mes" id="mes" class="w-full" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;">
                                @foreach(\App\Support\Meses::NOMBRES as $num => $nombre)
                                    <option value="{{ $num }}" {{ old('mes', $lectura->mes) == $num ? 'selected' : '' }}>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div style="flex: 1;">
                            <label for="gestion" class="block text-sm font-medium text-gray-700 mb-2">Gestión (Año)</label>
                            <input type="number" name="gestion" id="gestion" value="{{ $lectura->gestion }}" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;">
                        </div>
                    </div>

                    <div style="display: flex; gap: 15px; margin-top: 20px; margin-bottom: 20px;">
                        <div style="flex: 1;">
                            <label for="lectura_anterior" class="block text-sm font-medium text-gray-700 mb-2">Lectura Anterior (m³)</label>
                            <input type="number" step="any" min="0" name="lectura_anterior" id="lectura_anterior" value="{{ old('lectura_anterior', $lectura->lectura_anterior) }}" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;">
                            <p id="ayuda_anterior" class="mt-1.5 text-xs font-bold text-sky-700"></p>
                        </div>

                        <div style="flex: 1;">
                            <label for="lectura_actual" class="block text-sm font-medium text-gray-700 mb-2">Lectura Actual (m³)</label>
                            <input type="number" step="any" name="lectura_actual" id="lectura_actual" value="{{ old('lectura_actual', $lectura->lectura_actual) }}" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="observacion" class="block text-sm font-medium text-gray-700 mb-2">Observación (opcional)</label>
                        <input type="text" name="observacion" id="observacion" maxlength="255" value="{{ old('observacion', $lectura->observacion) }}"
                               placeholder="Ej.: Medidor dañado, posible fuga…" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;">
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 30px;">
                        <a href="{{ route('lecturas.index') }}" style="background-color: #6b7280; color: white; padding: 10px 20px; border-radius: 6px; font-weight: bold; text-decoration: none;">
                            Cancelar
                        </a>
                        <button type="submit" style="background-color: #2563eb; color: white; padding: 10px 20px; border-radius: 6px; font-weight: bold; border: none; cursor: pointer;">
                            Guardar Cambios
                        </button>
                    </div>
                </form>

                {{-- Foto del medidor (formulario aparte: se sube al momento) --}}
                <div class="mt-6 pt-6 border-t border-gray-100">
                    <h3 class="text-sm font-bold text-gray-700 mb-3">📷 Foto del medidor</h3>
                    @if($lectura->foto)
                        <a href="{{ route('lecturas.foto', $lectura) }}" target="_blank">
                            <img src="{{ route('lecturas.foto', $lectura) }}" alt="Foto del medidor" class="w-48 h-48 object-cover rounded-xl border border-gray-200 shadow-sm mb-3">
                        </a>
                    @else
                        <p class="text-xs text-gray-500 mb-3">Esta lectura no tiene foto.</p>
                    @endif
                    <form action="{{ route('lecturas.foto.store', $lectura) }}" method="POST" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                        @csrf
                        <input type="file" name="foto" accept="image/*" required class="text-sm">
                        <button type="submit" style="background-color: #0369a1; color: white; padding: 8px 16px; border-radius: 6px; font-weight: bold; border: none; cursor: pointer;">
                            {{ $lectura->foto ? 'Reemplazar foto' : 'Subir foto' }}
                        </button>
                    </form>
                    @error('foto')<p class="mt-1 text-xs font-bold text-red-600">{{ $message }}</p>@enderror
                </div>

            </div>
        </div>
    </div>

    <script>
        const inputBuscar = document.getElementById('buscar_socio');
        const selectSocios = document.getElementById('user_id');
        const inputAnterior = document.getElementById('lectura_anterior');

        // 1. Evento de búsqueda de socio en vivo
        inputBuscar.addEventListener('input', function() {
            const terminoBusqueda = this.value.toLowerCase().trim();
            const opciones = selectSocios.options;
            let primerMatch = null;

            for (let i = 0; i < opciones.length; i++) {
                const textoOpcion = opciones[i].text.toLowerCase();
                
                if (textoOpcion.includes(terminoBusqueda)) {
                    opciones[i].style.display = ""; 
                    if (!primerMatch) primerMatch = opciones[i];
                } else {
                    opciones[i].style.display = "none"; 
                }
            }

            if (primerMatch && terminoBusqueda !== "") {
                selectSocios.value = primerMatch.value;
                obtenerLecturaPrevia();
            }
        });

    </script>
    @include('lecturas._lectura_previa', ['ignorar' => $lectura->id])
</x-app-layout>