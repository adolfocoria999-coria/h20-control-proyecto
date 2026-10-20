@php
    $input = 'w-full rounded-xl border border-sky-200 px-4 py-3 focus:ring-2 focus:ring-blue-500 font-bold text-blue-900 bg-sky-50';
@endphp

<x-app-layout>
    <div class="py-10 bg-sky-200/70 min-h-screen">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div>
                <h2 class="font-extrabold text-3xl text-blue-950 leading-tight">Respaldos</h2>
                <p class="text-sm text-sky-900 mt-1 font-bold">
                    Copia completa de la base de datos que se descarga a tu dispositivo. No queda guardada en el servidor:
                    guárdala en un lugar seguro (otra computadora, un pendrive o tu nube personal).
                </p>
            </div>

            <x-mensajes-sesion />
            <x-errores-formulario />

            {{-- Estado del último respaldo --}}
            @if(! $ultimo)
                <div class="p-4 bg-sky-50 border-l-4 border-sky-500 rounded-r-xl text-sm font-bold text-sky-900">
                    💾 Aún no descargaste ningún respaldo. Te recomendamos descargar uno ahora y luego una vez por semana,
                    así siempre tendrás una copia de seguridad fuera del servidor.
                </div>
            @elseif($ultimo['dias'] >= $diasAviso)
                <div class="p-4 bg-amber-50 border-l-4 border-amber-400 rounded-r-xl text-sm font-bold text-amber-900">
                    💾 Tu último respaldo es del {{ $ultimo['fecha']->format('d/m/Y') }} (hace {{ $ultimo['dias'] }} días, por {{ $ultimo['por'] }}).
                    Es buen momento para descargar uno nuevo.
                </div>
            @else
                <div class="p-4 bg-emerald-50 border-l-4 border-emerald-600 rounded-r-xl text-sm font-bold text-emerald-900">
                    ✅ Último respaldo: {{ $ultimo['fecha']->format('d/m/Y H:i') }}, por {{ $ultimo['por'] }}{{ $ultimo['dias'] ? " (hace {$ultimo['dias']} día(s))" : ' (hoy)' }}.
                </div>
            @endif

            {{-- Descargar --}}
            <div class="bg-white p-6 rounded-2xl shadow-xl border border-sky-100 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-lg font-black text-blue-950">📥 Descargar respaldo completo</h3>
                    <p class="text-xs font-bold text-sky-800 mt-1 mb-3">Incluye todo lo que hay hoy en el sistema:</p>
                    <ul class="text-sm text-blue-950 space-y-1">
                        @foreach($contenido as $que => $cantidad)
                            <li class="flex justify-between gap-3 border-b border-sky-50 pb-1">
                                <span>{{ $que }}</span><span class="font-black">{{ $cantidad }}</span>
                            </li>
                        @endforeach
                        <li class="text-xs text-sky-700 pt-1">Además tarifas, ajustes y roles. No incluye sesiones abiertas ni datos temporales.</li>
                    </ul>
                </div>
                <form method="POST" action="{{ route('respaldos.descargar') }}" class="self-end">
                    @csrf
                    <p class="text-xs font-bold text-amber-800 mb-3">
                        🔒 El archivo contiene datos personales de todos los socios. Por seguridad, confirma tu contraseña.
                    </p>
                    <label for="password_descarga" class="block text-sm font-extrabold text-blue-950 mb-2">Tu contraseña</label>
                    <input type="password" id="password_descarga" name="password" required autocomplete="current-password" class="{{ $input }}">
                    <button type="submit" class="mt-3 w-full bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-3 rounded-xl font-extrabold text-sm shadow-md">
                        📥 Descargar respaldo
                    </button>
                </form>
            </div>

            {{-- Restaurar (solo superadmin) --}}
            @if(auth()->user()->esSuperAdmin())
                <div class="bg-white p-6 rounded-2xl shadow-xl border-2 border-red-200">
                    <h3 class="text-lg font-black text-red-800">♻️ Restaurar un respaldo</h3>
                    <p class="text-sm font-bold text-red-900 mt-1">
                        Reemplaza <span class="underline">todos</span> los datos actuales por los del archivo. Lo que se haya registrado
                        después de ese respaldo se pierde. Antes de restaurar, descarga un respaldo de lo que hay hoy.
                    </p>
                    <form method="POST" action="{{ route('respaldos.restaurar') }}" enctype="multipart/form-data"
                          class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4"
                          onsubmit="return confirm('¿Reemplazar TODOS los datos por los del respaldo? Esta acción no se puede deshacer.')">
                        @csrf
                        <div class="md:col-span-3">
                            <label for="archivo" class="block text-sm font-extrabold text-blue-950 mb-2">Archivo de respaldo (.json.gz)</label>
                            <input type="file" id="archivo" name="archivo" accept=".gz,application/gzip" required class="text-sm">
                        </div>
                        <div>
                            <label for="confirmacion" class="block text-sm font-extrabold text-blue-950 mb-2">Escribe RESTAURAR</label>
                            <input type="text" id="confirmacion" name="confirmacion" required autocomplete="off" class="{{ $input }}" placeholder="RESTAURAR">
                        </div>
                        <div>
                            <label for="password_restaurar" class="block text-sm font-extrabold text-blue-950 mb-2">Tu contraseña</label>
                            <input type="password" id="password_restaurar" name="password" required autocomplete="current-password" class="{{ $input }}">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white px-5 py-3 rounded-xl font-extrabold text-sm shadow-md">
                                ♻️ Restaurar
                            </button>
                        </div>
                    </form>
                    <p class="text-xs text-gray-500 mt-3">Al terminar se cierra la sesión y hay que volver a ingresar (los usuarios también se restauran).</p>
                </div>
            @endif

            {{-- Historial de respaldos --}}
            <div class="bg-white rounded-2xl shadow-xl border border-sky-100 overflow-hidden">
                <h3 class="px-6 py-4 text-lg font-black text-blue-950 border-b border-sky-100">Últimos respaldos y restauraciones</h3>
                <table class="tabla-responsiva min-w-full divide-y divide-sky-100 text-sm">
                    <thead class="bg-blue-900 text-white text-xs font-bold uppercase tracking-wider">
                        <tr>
                            <th class="px-5 py-3 text-left">Fecha</th>
                            <th class="px-5 py-3 text-left">Qué se hizo</th>
                            <th class="px-5 py-3 text-left">Quién</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sky-100 text-blue-950">
                        @forelse($historial as $registro)
                            <tr>
                                <td data-label="Fecha" class="px-5 py-3 whitespace-nowrap font-bold">{{ $registro->created_at->format('d/m/Y H:i') }}</td>
                                <td data-label="Qué se hizo" class="px-5 py-3">{{ $registro->descripcion }}</td>
                                <td data-label="Quién" class="px-5 py-3 text-xs font-bold text-sky-800">{{ $registro->usuario_nombre }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-5 py-8 text-center font-bold text-sky-700">Todavía no hay respaldos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
