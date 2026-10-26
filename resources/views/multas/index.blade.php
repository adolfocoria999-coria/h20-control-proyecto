<x-app-layout>
    <div class="py-10 bg-sky-200/70 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Encabezado y Acciones -->
            <!-- Título arriba; los botones en una sola fila debajo, todos del mismo alto -->
            @php $boton = 'h-12 inline-flex items-center justify-center gap-2 whitespace-nowrap px-5 rounded-xl font-extrabold text-sm shadow-md transition-colors'; @endphp
            <div class="mb-8 space-y-4">
                <div>
                    <h1 class="text-3xl font-black text-blue-950">Multas y Aportes</h1>
                    <p class="text-sm font-bold text-sky-800">Multas por infracciones y aportes para actividades de la OTB (aniversario, mantenimiento, compras...).</p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    @if($esGestion)
                        <a href="{{ route('multas.create') }}" class="{{ $boton }} bg-blue-700 hover:bg-blue-800 text-white">
                            ➕ Registrar Nueva Multa
                        </a>
                        <a href="{{ route('aportes.create') }}" class="{{ $boton }} bg-violet-600 hover:bg-violet-700 text-white">
                            🎉 Nuevo aporte
                        </a>
                        <a href="{{ route('tarifas-multas.index') }}" class="{{ $boton }} bg-white hover:bg-sky-50 text-blue-900 border-2 border-blue-200">
                            ⚙️ Tarifas de multas
                        </a>
                    @endif

                    <!-- Botón Exportar a Excel/CSV -->
                    <x-boton-excel :href="route('multas.exportar', request()->query())" class="h-12 py-0" />
                </div>
            </div>

            <x-mensajes-sesion class="mb-6" />

            <!-- Tarjetas de Resumen -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <!-- Tarjeta 1: Pendientes de Cobro -->
                <div class="bg-white rounded-2xl p-6 shadow-md border-l-8 border-amber-500 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-sky-600 uppercase tracking-wider">Pendientes de Cobro</p>
                        <h3 class="text-3xl font-black text-amber-600 mt-1">Bs. {{ number_format($totalPendiente ?? 0, 2) }}</h3>
                        <p class="text-xs font-bold text-gray-500 mt-1">{{ $cantPendientes ?? 0 }} multas y aportes pendientes</p>
                    </div>
                    <div class="p-3 bg-amber-100 rounded-full text-amber-500">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                </div>

                <!-- Tarjeta 2: Total Cobrado -->
                <div class="bg-white rounded-2xl p-6 shadow-md border-l-8 border-green-500 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-sky-600 uppercase tracking-wider">Total Cobrado</p>
                        <h3 class="text-3xl font-black text-green-600 mt-1">Bs. {{ number_format($totalCobrado ?? 0, 2) }}</h3>
                        <p class="text-xs font-bold text-gray-500 mt-1">Ingresado al Balance Comunal</p>
                    </div>
                    <div class="p-3 bg-green-100 rounded-full text-green-500">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>

                <!-- Tarjeta 3: Estado del Sistema -->
                <div class="bg-white rounded-2xl p-6 shadow-md border-l-8 border-blue-600 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-sky-600 uppercase tracking-wider">Estado del Sistema</p>
                        <h3 class="text-3xl font-black text-blue-950 mt-1">Activo</h3>
                        <p class="text-xs font-bold text-gray-500 mt-1">Control de multas y aportes de la OTB</p>
                    </div>
                    <div class="p-3 bg-blue-100 rounded-full text-blue-600">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            @if($esGestion && $aportes->isNotEmpty())
                <!-- Aportes: avance de cada actividad -->
                <div class="mb-8">
                    <h2 class="text-lg font-black text-blue-950 mb-3">🎉 Aportes</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        @foreach($aportes as $aporte)
                            @php $a = $aporte->avance; @endphp
                            <div class="bg-white rounded-2xl p-5 shadow-md border {{ $aporteFiltrado?->id === $aporte->id ? 'border-emerald-500 ring-2 ring-emerald-300' : 'border-sky-100' }}">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="font-black text-blue-950">{{ $aporte->titulo }}</p>
                                        <p class="text-xs font-bold text-sky-800">
                                            Bs. {{ number_format((float) $aporte->monto, 2) }} por socio
                                            @if($aporte->fecha_limite) · hasta el {{ $aporte->fecha_limite->format('d/m/Y') }} @endif
                                        </p>
                                    </div>
                                    @if($a['pagaron'] === 0)
                                        <form method="POST" action="{{ route('aportes.destroy', $aporte) }}"
                                              onsubmit="return confirm('¿Eliminar este aporte y las cuotas de todos los socios? Nadie lo pagó todavía.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-extrabold text-red-700 hover:text-red-900" title="Eliminar aporte">🗑️</button>
                                        </form>
                                    @endif
                                </div>
                                <div class="mt-3 h-2.5 bg-sky-100 rounded-full overflow-hidden" title="{{ $a['porcentaje'] }}% recaudado">
                                    <div class="h-full bg-emerald-500 rounded-full" style="width: {{ min(100, $a['porcentaje']) }}%"></div>
                                </div>
                                <p class="mt-2 text-sm font-black text-emerald-700">
                                    Bs. {{ number_format($a['recaudado'], 2) }} <span class="text-xs font-bold text-gray-500">de Bs. {{ number_format($a['esperado'], 2) }}</span>
                                </p>
                                <p class="text-xs font-bold text-gray-600">
                                    {{ $a['pagaron'] }} de {{ $a['socios'] - $a['condonados'] }} socios pagaron
                                    @if($a['condonados']) · {{ $a['condonados'] }} condonado(s) @endif
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2 text-xs font-extrabold">
                                    <a href="{{ route('multas.index', ['aporte' => $aporte->id, 'estado' => 'pendiente']) }}" class="px-3 py-1.5 rounded-lg bg-amber-100 text-amber-800 hover:bg-amber-200">⏳ Quién falta</a>
                                    <a href="{{ route('multas.index', ['aporte' => $aporte->id]) }}" class="px-3 py-1.5 rounded-lg bg-sky-100 text-blue-900 hover:bg-sky-200">Ver todos</a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($aporteFiltrado)
                <div class="mb-6 p-4 bg-emerald-50 border-l-4 border-emerald-600 rounded-r-xl flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-bold text-emerald-900">
                        Mostrando el aporte <span class="font-black">«{{ $aporteFiltrado->titulo }}»</span>
                        @if(request('estado') === 'pendiente') · solo quienes faltan pagar @endif
                    </p>
                    <a href="{{ route('multas.index') }}" class="text-xs font-extrabold text-emerald-800 underline">Ver todo</a>
                </div>
            @endif

            <!-- Filtros de Búsqueda -->
            <div class="bg-white p-6 rounded-2xl border border-sky-100 shadow-md mb-8">
                <form method="GET" action="{{ route('multas.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    @if(request('aporte'))
                        <input type="hidden" name="aporte" value="{{ request('aporte') }}">
                    @endif
                    <div>
                        <label class="block text-xs font-bold text-blue-950 mb-1">Tipo:</label>
                        <select name="tipo" class="w-full bg-sky-50 border border-sky-200 rounded-xl p-2.5 font-bold text-xs text-blue-950 focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Multas y aportes --</option>
                            <option value="multas" {{ request('tipo') === 'multas' ? 'selected' : '' }}>⚖️ Solo multas</option>
                            <option value="aportes" {{ request('tipo') === 'aportes' ? 'selected' : '' }}>🎉 Solo aportes</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-blue-950 mb-1">Estado de Pago:</label>
                        <select name="estado" class="w-full bg-sky-50 border border-sky-200 rounded-xl p-2.5 font-bold text-xs text-blue-950 focus:ring-2 focus:ring-blue-500">
                            <option value="">-- Todos los Estados --</option>
                            <option value="pendiente" {{ request('estado') === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                            <option value="pagado" {{ request('estado') === 'pagado' ? 'selected' : '' }}>Pagado</option>
                            <option value="condonado" {{ request('estado') === 'condonado' ? 'selected' : '' }}>Condonado</option>
                        </select>
                    </div>

                    @if($esGestion)
                        <div>
                            <label class="block text-xs font-bold text-blue-950 mb-1">Buscar Socio:</label>
                            <input type="text" name="buscar" value="{{ request('buscar') }}" placeholder="Nombre o Correo del socio..." class="w-full bg-sky-50 border border-sky-200 rounded-xl p-2.5 font-bold text-xs text-blue-950 focus:ring-2 focus:ring-blue-500">
                        </div>
                    @endif

                    <div class="flex items-end gap-2">
                        <button type="submit" class="bg-sky-600 hover:bg-sky-700 text-white font-bold px-4 py-2.5 rounded-xl text-xs transition-all w-full">
                            🔍 Filtrar
                        </button>
                        <a href="{{ route('multas.index') }}" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-4 py-2.5 rounded-xl text-xs transition-all text-center">
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>

            <!-- Tabla de Multas -->
            <div class="bg-white rounded-2xl border border-sky-100 shadow-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="tabla-responsiva w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-sky-900 text-white text-xs uppercase tracking-wider font-extrabold">
                                <th class="py-4 px-6">Socio / Afiliado</th>
                                <th class="py-4 px-6">Concepto / Motivo</th>
                                <th class="py-4 px-6 text-center">Monto (Bs.)</th>
                                <th class="py-4 px-6 text-center">Fecha</th>
                                <th class="py-4 px-6 text-center">Estado</th>
                                @if($esGestion)
                                    <th class="py-4 px-6 text-center">Acciones</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sky-100 text-sm font-semibold text-gray-700">
                            @forelse($multas as $m)
                                <tr class="hover:bg-sky-50 transition-all">
                                    <!-- Socio -->
                                    <td data-label="Socio / Afiliado" class="py-4 px-6">
                                        <p class="font-bold text-blue-950">{{ $m->socio->name ?? 'Usuario Desconocido' }}</p>
                                        <p class="text-xs text-gray-500">{{ $m->socio->email ?? 'Sin Email' }}</p>
                                    </td>

                                    <!-- Infracción -->
                                    <td data-label="Concepto / Motivo" class="py-4 px-6">
                                        @if($m->esAporte())
                                            <span class="inline-block mb-1 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[11px] font-black">🎉 Aporte</span>
                                        @else
                                            <span class="inline-block mb-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[11px] font-black">⚖️ Multa</span>
                                        @endif
                                        <p class="font-bold text-sky-900">
                                            @if($m->tipo_multa && $m->tipo_multa !== 'Multa')
                                                {{ $m->tipo_multa }}
                                            @elseif(optional($m->tarifa)->nombre)
                                                {{ $m->tarifa->nombre }}
                                            @else
                                                {{ $m->motivo ?? 'Multa' }}
                                            @endif
                                        </p>
                                        @if($m->motivo)
                                            <p class="text-xs text-gray-500 italic mt-0.5">Nota: {{ $m->motivo }}</p>
                                        @endif
                                    </td>

                                    <!-- Monto -->
                                    <td data-label="Monto (Bs.)" class="py-4 px-6 text-center font-extrabold text-emerald-700">
                                        Bs. {{ number_format($m->monto, 2) }}
                                    </td>

                                    <!-- Fecha -->
                                    <td data-label="Fecha" class="py-4 px-6 text-center text-xs text-gray-600">
                                        {{ $m->fecha_multa->format('d/m/Y') }}
                                        @if($m->esAporte() && $m->estado === 'pendiente' && $m->aporte?->fecha_limite)
                                            <div class="mt-0.5 font-bold {{ $m->aporte->fecha_limite->lt(today()) ? 'text-red-700' : 'text-sky-800' }}">Límite: {{ $m->aporte->fecha_limite->format('d/m/Y') }}</div>
                                        @endif
                                    </td>

                                    <!-- Estado -->
                                    <td data-label="Estado" class="py-4 px-6 text-center">
                                        @if($m->estado === 'pagado')
                                            <span class="px-3 py-1 bg-emerald-100 text-emerald-800 rounded-full text-xs font-black border border-emerald-200">
                                                ✅ Pagado
                                            </span>
                                            @if($m->metodo_pago)
                                                <div class="mt-1 text-xs font-bold text-sky-800">{{ $m->metodo_pago->icono() }} {{ $m->metodo_pago->etiqueta() }}</div>
                                            @endif
                                            <div class="mt-2">
                                                <x-boton-pdf chico :href="route('recibos.multa', $m)" title="Descargar recibo en PDF">Recibo</x-boton-pdf>
                                            </div>
                                        @elseif($m->estado === 'condonado')
                                            <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-black border border-purple-200">
                                                🔵 Condonado
                                            </span>
                                        @else
                                            <span class="px-3 py-1 bg-amber-100 text-amber-800 rounded-full text-xs font-black border border-amber-200">
                                                ⏳ Pendiente
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Acciones -->
                                    @if($esGestion)
                                        <td data-label="Acciones" class="py-4 px-6 text-center">
                                            <div class="flex flex-wrap justify-center items-center gap-2">
                                                @if($m->estado === 'pendiente')
                                                    <!-- Botón Pagar: abre el modal para elegir QR o efectivo -->
                                                    <x-boton-pagar :accion="route('multas.pagar', $m)"
                                                                   :concepto="$m->concepto.' – '.($m->socio->name ?? 'Socio')"
                                                                   :monto="'Bs. '.number_format((float) $m->monto, 2, ',', '.')">
                                                        Pagar
                                                    </x-boton-pagar>
                                                @endif

                                                @if($m->estado === 'pendiente')
                                                    <!-- Condonar: se exime al socio (no se cobra ni se borra) -->
                                                    <form action="{{ route('multas.condonar', $m) }}" method="POST" class="inline"
                                                          onsubmit="return confirm('¿Condonar {{ $m->esAporte() ? 'esta cuota del aporte' : 'esta multa' }}? El socio ya no la deberá.')">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="bg-purple-100 hover:bg-purple-200 text-purple-800 font-bold px-3 py-1.5 rounded-lg text-xs transition-all shadow" title="Eximir al socio de este cobro">
                                                            🔵 Condonar
                                                        </button>
                                                    </form>
                                                @endif

                                                @unless($m->esAporte())
                                                    <a href="{{ route('multas.edit', $m) }}" class="bg-white border border-sky-300 hover:bg-sky-50 text-blue-900 font-bold px-3 py-1.5 rounded-lg text-xs transition-all shadow" title="Editar multa">✏️ Editar</a>
                                                @endunless

                                                <!-- Botón Eliminar -->
                                                <form action="{{ route('multas.destroy', $m->id) }}" method="POST" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" onclick="return confirm('¿Está seguro de eliminar {{ $m->esAporte() ? 'esta cuota del aporte' : 'esta multa' }}? Esta acción no se puede deshacer.')" class="bg-red-600 hover:bg-red-700 text-white font-bold px-3 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1 shadow" title="Eliminar">
                                                        🗑️ Eliminar
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $esGestion ? 6 : 5 }}" class="py-8 text-center text-gray-500 font-bold">
                                        No se encontraron multas ni aportes.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                @if($multas->hasPages())
                    <div class="p-4 bg-sky-50 border-t border-sky-100">
                        {{ $multas->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
    <x-modal-pago />
</x-app-layout>