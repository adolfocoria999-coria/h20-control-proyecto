@php
    use App\Enums\RolUsuario;
    use App\Models\Actividad;
    use App\Support\Meses;

    $esSuperAdmin = auth()->user()->esSuperAdmin();
    $periodo = $filtros['mes'] ? Meses::nombre($filtros['mes']).' '.$filtros['gestion'] : 'todo '.$filtros['gestion'];

    $colorAccion = fn (string $accion) => match ($accion) {
        'creado' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
        'actualizado' => 'bg-blue-100 text-blue-800 border-blue-200',
        'eliminado', 'baja' => 'bg-red-100 text-red-800 border-red-200',
        'pago' => 'bg-green-100 text-green-800 border-green-200',
        'condonacion' => 'bg-purple-100 text-purple-800 border-purple-200',
        'bloqueo' => 'bg-amber-100 text-amber-800 border-amber-200',
        'cambio_contrasena' => 'bg-sky-100 text-sky-800 border-sky-200',
        default => 'bg-gray-100 text-gray-700 border-gray-200',
    };

    $input = 'w-full px-4 py-2.5 bg-white/90 rounded-xl border border-sky-300 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500/50 text-sm font-semibold text-blue-950 shadow-sm';
    $label = 'block text-xs font-black uppercase text-blue-950 tracking-wider mb-1.5 ml-1';
@endphp

<x-app-layout>
    <div class="py-12 bg-sky-200/70 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Encabezado -->
            <div class="md:flex md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="font-extrabold text-3xl text-blue-950 leading-tight tracking-tight">Historial de Actividades</h2>
                    <p class="text-sm text-sky-900 mt-1 font-bold">Registro de las acciones de administradores y socios en el sistema</p>
                </div>
                <x-boton-excel :href="route('historial.exportar', request()->query())" class="mt-4 md:mt-0" />
            </div>

            @if(session('success'))
                <div class="p-4 bg-sky-100/90 border-l-4 border-sky-600 rounded-r-xl shadow-md">
                    <span class="text-blue-950 font-bold">{{ session('success') }}</span>
                </div>
            @endif

            <!-- Apartados -->
            <div class="flex gap-2 border-b-2 border-sky-300">
                @foreach([Actividad::SECCION_ADMIN => '🛡️ Administración', Actividad::SECCION_USUARIO => '👥 Usuarios'] as $clave => $nombre)
                    @php $activa = $filtros['seccion'] === $clave; @endphp
                    <a href="{{ route('historial.index', array_merge(request()->except(['page', 'seccion']), ['seccion' => $clave])) }}"
                       class="-mb-0.5 px-4 sm:px-6 py-3 rounded-t-xl font-extrabold text-sm border-2 border-b-0 {{ $activa ? 'bg-white text-blue-900 border-sky-300' : 'bg-sky-100/60 text-sky-800 border-transparent hover:bg-white/70' }}">
                        {{ $nombre }}
                        <span class="ml-1 px-2 py-0.5 rounded-full text-xs {{ $activa ? 'bg-blue-700 text-white' : 'bg-sky-200 text-sky-900' }}">
                            {{ number_format($totalesPorSeccion[$clave] ?? 0) }}
                        </span>
                    </a>
                @endforeach
            </div>

            <!-- Filtros -->
            <form method="GET" action="{{ route('historial.index') }}" class="bg-white p-5 rounded-2xl shadow-md border border-sky-100">
                <input type="hidden" name="seccion" value="{{ $filtros['seccion'] }}">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div>
                        <label for="gestion" class="{{ $label }}">Año</label>
                        <select name="gestion" id="gestion" onchange="this.form.submit()" class="{{ $input }} cursor-pointer">
                            @foreach($gestiones as $anio)
                                <option value="{{ $anio }}" {{ $filtros['gestion'] == $anio ? 'selected' : '' }}>{{ $anio }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="mes" class="{{ $label }}">Mes</label>
                        <select name="mes" id="mes" onchange="this.form.submit()" class="{{ $input }} cursor-pointer">
                            <option value="todos" {{ $filtros['mes'] === null ? 'selected' : '' }}>Todo el año</option>
                            @foreach(Meses::NOMBRES as $num => $nombre)
                                <option value="{{ $num }}" {{ $filtros['mes'] === $num ? 'selected' : '' }}>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="modulo" class="{{ $label }}">Módulo</label>
                        <select name="modulo" id="modulo" onchange="this.form.submit()" class="{{ $input }} cursor-pointer">
                            <option value="">Todos</option>
                            @foreach(Actividad::MODULOS as $clave => $nombre)
                                <option value="{{ $clave }}" {{ $filtros['modulo'] === $clave ? 'selected' : '' }}>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="accion" class="{{ $label }}">Acción</label>
                        <select name="accion" id="accion" onchange="this.form.submit()" class="{{ $input }} cursor-pointer">
                            <option value="">Todas</option>
                            @foreach(array_keys(Actividad::ACCIONES) as $clave)
                                <option value="{{ $clave }}" {{ $filtros['accion'] === $clave ? 'selected' : '' }}>{{ (new Actividad(['accion' => $clave]))->accionEtiqueta() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="buscar" class="{{ $label }}">Buscar</label>
                        <input type="text" name="buscar" id="buscar" value="{{ $filtros['buscar'] }}" placeholder="🔍 Usuario o descripción..." class="{{ $input }}">
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3 mt-4">
                    <button type="submit" class="bg-blue-700 hover:bg-blue-800 text-white px-5 py-2.5 rounded-xl font-extrabold text-sm shadow-md">🔍 Buscar</button>
                    <a href="{{ route('historial.index', ['seccion' => $filtros['seccion']]) }}" class="text-xs font-bold text-red-500 hover:underline">Volver al mes actual</a>
                    <span class="text-xs font-bold text-sky-800 sm:ml-auto">
                        {{ number_format($actividades->total()) }} registro(s) en {{ $periodo }}
                    </span>
                </div>
            </form>

            <!-- Tabla -->
            <div class="bg-white rounded-2xl shadow-xl shadow-sky-900/10 border border-sky-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="tabla-responsiva min-w-full divide-y divide-sky-100">
                        <thead class="bg-blue-900 text-white text-xs font-bold uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-4 text-left whitespace-nowrap">Fecha y hora</th>
                                <th class="px-4 py-4 text-left">Usuario</th>
                                <th class="px-4 py-4 text-left">Acción</th>
                                <th class="px-4 py-4 text-left">Módulo</th>
                                <th class="px-4 py-4 text-left">Descripción</th>
                                <th class="px-4 py-4 text-left">IP</th>
                                @if($esSuperAdmin)
                                    <th class="px-4 py-4 text-right">Acciones</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sky-100/70 bg-white text-sm">
                            @forelse($actividades as $actividad)
                                <tr class="hover:bg-sky-50 align-top">
                                    <td data-label="Fecha y hora" class="px-4 py-3 whitespace-nowrap font-bold text-blue-950">
                                        {{ $actividad->created_at->format('d/m/Y') }}
                                        <span class="text-sky-700 font-semibold">{{ $actividad->created_at->format('H:i') }}</span>
                                    </td>
                                    <td data-label="Usuario" class="px-4 py-3">
                                        <div class="font-extrabold text-blue-950">{{ $actividad->usuario_nombre }}</div>
                                        <div class="text-xs font-bold text-sky-700">{{ RolUsuario::etiqueta($actividad->rol) }}</div>
                                    </td>
                                    <td data-label="Acción" class="px-4 py-3">
                                        <span class="px-2.5 py-1 inline-flex whitespace-nowrap text-xs font-extrabold rounded-full border {{ $colorAccion($actividad->accion) }}">
                                            {{ $actividad->accionEtiqueta() }}
                                        </span>
                                    </td>
                                    <td data-label="Módulo" class="px-4 py-3 font-bold text-sky-900">{{ $actividad->moduloEtiqueta() }}</td>
                                    <td data-label="Descripción" class="px-4 py-3 text-blue-950">
                                        <div>{{ $actividad->descripcion }}</div>
                                        @if($actividad->cambios)
                                            <details class="mt-1 text-xs">
                                                <summary class="cursor-pointer font-bold text-blue-700">Ver detalle ({{ count($actividad->cambios) }})</summary>
                                                <ul class="mt-1.5 space-y-1 text-left">
                                                    @foreach($actividad->cambios as $campo => $valor)
                                                        @php
                                                            $nombreCampo = __('validation.attributes.'.$campo);
                                                            $nombreCampo = $nombreCampo === 'validation.attributes.'.$campo ? str_replace('_', ' ', $campo) : $nombreCampo;
                                                            $mostrar = fn ($v) => $v === null || $v === '' ? '—' : (is_scalar($v) ? (string) $v : json_encode($v));
                                                        @endphp
                                                        <li>
                                                            <span class="font-extrabold text-blue-900">{{ ucfirst($nombreCampo) }}:</span>
                                                            @if(is_array($valor) && array_key_exists('antes', $valor))
                                                                @if($valor['antes'] !== null)<span class="line-through text-red-600">{{ $mostrar($valor['antes']) }}</span>@endif
                                                                @if($valor['antes'] !== null && $valor['despues'] !== null) → @endif
                                                                @if($valor['despues'] !== null)<span class="text-emerald-700 font-bold">{{ $mostrar($valor['despues']) }}</span>@endif
                                                            @else
                                                                {{ $mostrar($valor) }}
                                                            @endif
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </details>
                                        @endif
                                    </td>
                                    <td data-label="IP" class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ $actividad->ip ?? '—' }}</td>
                                    @if($esSuperAdmin)
                                        <td data-label="Acciones" class="px-4 py-3 text-right">
                                            <form action="{{ route('historial.destroy', $actividad) }}" method="POST" class="inline" onsubmit="return confirm('¿Eliminar este registro del historial?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="px-3 py-1.5 whitespace-nowrap bg-red-500 hover:bg-red-600 text-white rounded-lg text-xs font-extrabold">🗑️ Eliminar</button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $esSuperAdmin ? 7 : 6 }}" class="px-6 py-12 text-center text-sky-700 font-bold">
                                        No hay actividades registradas en {{ $periodo }} con estos filtros.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div>{{ $actividades->links() }}</div>

            <!-- Depuración por periodo (solo superadministrador) -->
            @if($esSuperAdmin)
                @php $totalPeriodo = $totalesPorSeccion[$filtros['seccion']] ?? 0; @endphp
                <details class="bg-white rounded-2xl shadow-md border border-red-200 p-5">
                    <summary class="cursor-pointer font-extrabold text-red-700">🗑️ Eliminar registros de un periodo</summary>
                    <div class="mt-4 space-y-3 text-sm text-blue-950">
                        <p>
                            Se eliminarán <strong>todos</strong> los registros del apartado
                            <strong>{{ $filtros['seccion'] === Actividad::SECCION_ADMIN ? 'Administración' : 'Usuarios' }}</strong>
                            de <strong>{{ $periodo }}</strong>: <strong>{{ number_format($totalPeriodo) }} registro(s)</strong>
                            (sin importar los filtros de módulo, acción o búsqueda). Esta acción no se puede deshacer; exporta a Excel antes si necesitas conservarlos.
                        </p>
                        <form action="{{ route('historial.destroy-periodo') }}" method="POST"
                              onsubmit="return confirm('¿Eliminar {{ $totalPeriodo }} registro(s) de {{ $periodo }}? No se puede deshacer.')">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="seccion" value="{{ $filtros['seccion'] }}">
                            <input type="hidden" name="gestion" value="{{ $filtros['gestion'] }}">
                            <input type="hidden" name="mes" value="{{ $filtros['mes'] }}">
                            <button type="submit" @disabled($totalPeriodo === 0)
                                    class="bg-red-600 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed text-white px-5 py-2.5 rounded-xl font-extrabold text-sm shadow-md">
                                Eliminar {{ number_format($totalPeriodo) }} registro(s) de {{ $periodo }}
                            </button>
                        </form>
                    </div>
                </details>
            @endif

        </div>
    </div>
</x-app-layout>
