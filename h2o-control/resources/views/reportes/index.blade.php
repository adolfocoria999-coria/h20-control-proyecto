<x-app-layout>
    <div class="py-10 bg-sky-200/70 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Encabezado y Acciones -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
                <div>
                    <h1 class="text-3xl font-black text-blue-950">Módulo de Reportes Consolidados H2O</h1>
                    <p class="text-sm font-bold text-sky-800">Resumen gerencial de recaudación por consumo de agua, multas y morosidad comunal.</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <x-boton-excel :href="route('reportes.exportar', request()->query())" />
                </div>
            </div>

            <x-mensajes-sesion class="mb-6" />

            <!-- Tarjetas de Resumen KPI -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <!-- Tarjeta 1: Pendientes de Cobro -->
                <div class="bg-white rounded-2xl p-6 shadow-md border-l-8 border-amber-500 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-sky-600 uppercase tracking-wider">Pendientes de Cobro Total</p>
                        <h3 class="text-3xl font-black text-amber-600 mt-1">Bs. {{ number_format($totalGeneralPorCobrar ?? 0, 2) }}</h3>
                        <p class="text-xs font-bold text-gray-500 mt-1">Agua: Bs. {{ number_format($totalAguaPendiente ?? 0, 2) }} | Multas: Bs. {{ number_format($totalMultasPendientes ?? 0, 2) }}</p>
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
                        <p class="text-sm font-bold text-sky-600 uppercase tracking-wider">Total Cobrado (Ingresado)</p>
                        <h3 class="text-3xl font-black text-green-600 mt-1">Bs. {{ number_format($totalGeneralIngresado ?? 0, 2) }}</h3>
                        <p class="text-xs font-bold text-gray-500 mt-1">Ingresado al Balance Comunal</p>
                    </div>
                    <div class="p-3 bg-green-100 rounded-full text-green-500">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>

                <!-- Tarjeta 3: Socios Deudores -->
                <div class="bg-white rounded-2xl p-6 shadow-md border-l-8 border-red-600 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-sky-600 uppercase tracking-wider">Socios Deudores</p>
                        <h3 class="text-3xl font-black text-red-600 mt-1">{{ count($sociosMorosos ?? []) }}</h3>
                        <p class="text-xs font-bold text-gray-500 mt-1">Sujetos a notificación o sanción</p>
                    </div>
                    <div class="p-3 bg-red-100 rounded-full text-red-600">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Filtro de Búsqueda -->
            <div class="bg-white p-6 rounded-2xl border border-sky-100 shadow-md mb-8">
                <form method="GET" action="{{ route('reportes.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="block text-xs font-bold text-blue-950 mb-1">Filtrar por Mes:</label>
                        <select name="mes" class="w-full bg-sky-50 border border-sky-200 rounded-xl p-2.5 font-bold text-xs text-blue-950 focus:ring-2 focus:ring-blue-500 transition-all">
                            <option value="">-- Todos los Meses --</option>
                            <option value="1" {{ request('mes') == '1' ? 'selected' : '' }}>Enero</option>
                            <option value="2" {{ request('mes') == '2' ? 'selected' : '' }}>Febrero</option>
                            <option value="3" {{ request('mes') == '3' ? 'selected' : '' }}>Marzo</option>
                            <option value="4" {{ request('mes') == '4' ? 'selected' : '' }}>Abril</option>
                            <option value="5" {{ request('mes') == '5' ? 'selected' : '' }}>Mayo</option>
                            <option value="6" {{ request('mes') == '6' ? 'selected' : '' }}>Junio</option>
                            <option value="7" {{ request('mes') == '7' ? 'selected' : '' }}>Julio</option>
                            <option value="8" {{ request('mes') == '8' ? 'selected' : '' }}>Agosto</option>
                            <option value="9" {{ request('mes') == '9' ? 'selected' : '' }}>Septiembre</option>
                            <option value="10" {{ request('mes') == '10' ? 'selected' : '' }}>Octubre</option>
                            <option value="11" {{ request('mes') == '11' ? 'selected' : '' }}>Noviembre</option>
                            <option value="12" {{ request('mes') == '12' ? 'selected' : '' }}>Diciembre</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-blue-950 mb-1">Gestión / Año:</label>
                        <select name="gestion" class="w-full bg-sky-50 border border-sky-200 rounded-xl p-2.5 font-bold text-xs text-blue-950 focus:ring-2 focus:ring-blue-500 transition-all">
                            @foreach($gestiones as $anio)
                                <option value="{{ $anio }}" {{ $gestion == $anio ? 'selected' : '' }}>Gestión {{ $anio }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="bg-sky-600 hover:bg-sky-700 text-white font-bold px-6 py-2.5 rounded-xl text-xs transition-all w-full flex justify-center items-center gap-1 shadow-md">
                            🔍 Filtrar
                        </button>
                        <a href="{{ route('reportes.index') }}" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold px-4 py-2.5 rounded-xl text-xs transition-all flex items-center justify-center">
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>

            <!-- GRÁFICO CON BARRAS VERDES PARA PENDIENTE POR COBRAR -->
            <div class="bg-white rounded-2xl border border-sky-100 shadow-xl overflow-hidden mb-8 p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 pb-4 border-b border-sky-100 gap-2">
                    <div>
                        <h3 class="text-xl font-black text-blue-950">Balance Financiero de Recaudaciones</h3>
                        <p class="text-xs font-bold text-sky-800">
                            {{ request('mes') ? 'Mostrando el detalle acumulado del mes seleccionado' : 'Evolución mensual comparativa durante la gestión ' . $gestion }}
                        </p>
                    </div>
                    
                    <div class="flex flex-wrap items-center gap-3 text-xs font-extrabold bg-sky-50 p-2.5 rounded-xl border border-sky-100">
                        <span class="flex items-center gap-1.5 text-sky-900">
                            <span class="w-3 h-3 rounded-full bg-sky-600 inline-block shadow-sm"></span> Agua Cobrada
                        </span>
                        <span class="flex items-center gap-1.5 text-amber-800">
                            <span class="w-3 h-3 rounded-full bg-amber-500 inline-block shadow-sm"></span> Multas Cobradas
                        </span>
                        <span class="flex items-center gap-1.5 text-emerald-800">
                            <span class="w-3 h-3 rounded-full bg-emerald-500 inline-block shadow-sm"></span> Pendiente por Cobrar
                        </span>
                    </div>
                </div>

                <div class="w-full overflow-x-auto">
                    <div class="md:min-w-[600px] py-2">
                        <div id="chartUnificado"></div>
                    </div>
                </div>
            </div>

            <!-- Tabla Consolidada de Deudores -->
            <div class="bg-white rounded-2xl border border-sky-100 shadow-xl overflow-hidden">
                <div class="p-4 bg-sky-50 border-b border-sky-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-extrabold text-blue-950">Lista Consolidada de Deudores OTB</h2>
                        <p class="text-xs font-bold text-sky-800">Control unificado de meses en mora por consumo de agua y sanciones acumuladas</p>
                    </div>
                    <span class="bg-red-100 text-red-800 text-xs font-black px-3 py-1 rounded-full border border-red-200">
                        {{ count($sociosMorosos ?? []) }} Deudores
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="tabla-responsiva w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-sky-900 text-white text-xs uppercase tracking-wider font-extrabold">
                                <th class="py-4 px-6">Socio / Afiliado</th>
                                <th class="py-4 px-6 text-center">Meses</th>
                                <th class="py-4 px-6 text-right">Monto Agua (Bs.)</th>
                                <th class="py-4 px-6 text-right">Monto Multas (Bs.)</th>
                                <th class="py-4 px-6 text-right">Total Acumulado</th>
                                <th class="py-4 px-6 text-center">Acción Recomendada</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sky-100 text-sm font-semibold text-gray-700">
                            @forelse($sociosMorosos as $socio)
                                <tr class="hover:bg-sky-50 transition-all">
                                    <td data-label="Socio / Afiliado" class="py-4 px-6">
                                        <p class="font-bold text-blue-950">{{ $socio->name }}</p>
                                        <p class="text-xs text-gray-500">CI: {{ $socio->ci ?? 'Sin CI' }}</p>
                                    </td>

                                    <!-- Escala de colores para los meses en mora -->
                                    <td data-label="Meses" class="py-4 px-6 text-center">
                                        @if($socio->cant_meses_mora === 0)
                                            <span class="px-3 py-1 bg-gray-100 text-gray-700 border border-gray-300 rounded-full text-xs font-black shadow-sm">
                                                Solo multas
                                            </span>
                                        @elseif(\App\Services\AvisoDeuda::esCorte($socio))
                                            <!-- 3 o más meses: Fondo rojo claro con letras rojo oscuro -->
                                            <span class="px-3 py-1 bg-red-100 text-red-700 border border-red-300 rounded-full text-xs font-black shadow-sm">
                                                {{ $socio->cant_meses_mora }} Meses
                                            </span>
                                        @elseif($socio->cant_meses_mora >= 2)
                                            <!-- 2 meses o más (sin llegar al corte): AMARILLO -->
                                            <span class="px-3 py-1 bg-amber-100 text-amber-800 border border-amber-300 rounded-full text-xs font-black shadow-sm">
                                                {{ $socio->cant_meses_mora }} Meses
                                            </span>
                                        @else
                                            <!-- 1 mes: VERDE -->
                                            <span class="px-3 py-1 bg-emerald-100 text-emerald-800 border border-emerald-300 rounded-full text-xs font-black shadow-sm">
                                                1 Mes
                                            </span>
                                        @endif
                                    </td>

                                    <td data-label="Monto Agua (Bs.)" class="py-4 px-6 text-right font-extrabold text-sky-900">
                                        Bs. {{ number_format($socio->deuda_agua, 2) }}
                                    </td>

                                    <td data-label="Monto Multas (Bs.)" class="py-4 px-6 text-right font-extrabold text-amber-600">
                                        Bs. {{ number_format($socio->deuda_multas, 2) }}
                                    </td>

                                    <td data-label="Total Acumulado" class="py-4 px-6 text-right font-black text-red-600">
                                        Bs. {{ number_format($socio->deuda_total, 2) }}
                                    </td>

                                    <td data-label="Acción Recomendada" class="py-4 px-6 text-center">
                                        @php $corte = \App\Services\AvisoDeuda::esCorte($socio); @endphp
                                        @if(\App\Support\WhatsApp::numero($socio->telefono))
                                            <a href="{{ route('reportes.notificar', ['socio' => $socio->id, 'gestion' => $gestion, 'mes' => $mesSel ?: null]) }}"
                                               target="_blank" rel="noopener"
                                               title="Abre WhatsApp con el mensaje listo para enviar a {{ $socio->telefono }}"
                                               class="inline-flex items-center gap-1.5 whitespace-nowrap px-3 py-1.5 rounded-lg text-xs font-black border shadow-sm {{ $corte ? 'bg-red-600 hover:bg-red-700 text-white border-red-700' : 'bg-amber-100 hover:bg-amber-200 text-amber-900 border-amber-300' }}">
                                                {{ $corte ? '🚨 Aviso de corte' : '⚠️ Notificar' }}
                                                <span class="text-[10px] font-extrabold opacity-80">WhatsApp</span>
                                            </a>
                                        @else
                                            <span class="inline-block px-3 py-1.5 rounded-lg text-xs font-black border bg-gray-100 text-gray-500 border-gray-200" title="Registra su celular en Socios para poder avisarle">
                                                {{ $corte ? '🚨 Corte' : '⚠️ Notificar' }} · 📵 Sin celular
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-gray-500 font-bold">
                                        ✨ No hay deudores registrados para el período seleccionado.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- Script ApexCharts optimizado para etiquetas legibles y montos grandes -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var esMesEspecifico = {{ request('mes') ? 'true' : 'false' }};

            var optionsUnificado = {
                chart: { 
                    type: 'bar', 
                    height: 380, 
                    stacked: true,
                    toolbar: { show: false },
                    fontFamily: 'Figtree, system-ui, sans-serif'
                },
                series: [
                    { name: 'Cobrado Agua', data: @json($datosAguaCobrada) },
                    { name: 'Cobrado Multas', data: @json($datosMultasCobradas) },
                    { name: 'Pendiente por Cobrar', data: @json($datosPendientes) }
                ],
                xaxis: { 
                    categories: @json($mesesGrafico),
                    labels: { 
                        style: { 
                            fontWeight: 800, 
                            colors: '#0f172a',
                            fontSize: '12px' 
                        } 
                    },
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                yaxis: {
                    labels: {
                        style: { fontWeight: 600, colors: '#64748b' },
                        formatter: function (val) {
                            if (val >= 1000) {
                                return 'Bs. ' + (val / 1000).toFixed(1) + 'k';
                            }
                            return 'Bs. ' + val.toFixed(0);
                        }
                    }
                },
                colors: ['#0284c7', '#f59e0b', '#10b981'],
                plotOptions: { 
                    bar: { 
                        horizontal: false, 
                        columnWidth: esMesEspecifico ? '35%' : '55%', // Barras más anchas para que entren los números
                        borderRadius: 6,
                        dataLabels: { 
                            position: 'center',
                            hideOverflowingLabels: true // Evita que chocen etiquetas cuando la barra es muy pequeña
                        }
                    } 
                },
                dataLabels: { 
                    enabled: true,
                    formatter: function (val) {
                        if (!val || val <= 0) return '';
                        // Formato compacto si el número es igual o mayor a 10,000 (ej: Bs. 10k)
                        if (val >= 10000) {
                            return 'Bs. ' + (val / 1000).toFixed(1) + 'k';
                        }
                        return 'Bs. ' + val.toLocaleString('es-BO', { maximumFractionDigits: 0 });
                    },
                    style: { 
                        fontSize: '11px', 
                        fontWeight: '800',
                        colors: ['#ffffff'] 
                    }
                },
                grid: {
                    borderColor: '#f1f5f9',
                    strokeDashArray: 4
                },
                tooltip: {
                    theme: 'light',
                    y: {
                        // En la ventana emergente SIEMPRE se muestra la cifra completa con decimales
                        formatter: function (val) {
                            return 'Bs. ' + Number(val).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                    }
                },
                legend: { show: false }
            };

            new ApexCharts(document.querySelector("#chartUnificado"), optionsUnificado).render();
        });
    </script>
</x-app-layout>