<!-- CDN de ApexCharts -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<x-app-layout>
    <div class="py-12 bg-sky-200/70 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            
            <!-- Encabezado Principal -->
            <div class="md:flex md:items-center md:justify-between gap-4 mb-6">
                <div>
                    <h2 class="font-extrabold text-3xl text-blue-950 leading-tight tracking-tight drop-shadow-sm">
                        Portal de Finanzas y Transparencia
                    </h2>
                    <p class="text-sm text-sky-900 mt-1 font-bold">Estado de cuentas y balances mensuales de la OTB</p>
                </div>
                
                @php
                    $esSuperAdmin = Auth::user()->esSuperAdmin();
                    $esAdmin = Auth::user()->esAdmin();
                @endphp

                <div class="mt-4 md:mt-0 flex flex-wrap gap-3">
                    <x-boton-excel :href="route('finanzas.exportar', request()->query())" />

                    @if($esAdmin)
                    <a href="{{ route('arqueo.index') }}" class="inline-flex items-center justify-center gap-2 whitespace-nowrap bg-white hover:bg-sky-50 text-blue-900 border-2 border-blue-200 px-5 py-3 rounded-xl font-extrabold text-sm shadow-md">
                        🧾 Arqueo mensual
                    </a>
                    <a href="{{ route('finanzas.create') }}" class="inline-flex items-center justify-center bg-blue-700 hover:bg-blue-800 text-white px-6 py-3.5 rounded-xl font-extrabold shadow-lg shadow-blue-400/50 transition-all duration-200 ease-in-out transform hover:-translate-y-0.5">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Registrar Balance Mensual
                    </a>
                    @endif
                </div>
            </div>

            <!-- Widgets de Resumen -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <!-- Caja Actual -->
                <div class="bg-white rounded-2xl p-6 shadow-md border-l-8 border-blue-600 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-bold text-sky-600 uppercase tracking-wider">Caja Actual (Total)</p>
                        <h3 class="text-3xl font-black text-blue-950 mt-1">Bs. {{ number_format($cajaActual ?? 0, 2) }}</h3>
                    </div>
                    <div class="p-3 bg-blue-100 rounded-full text-blue-600">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                </div>

                <!-- Ingresos -->
                <div class="bg-white rounded-2xl p-6 shadow-md border-l-8 border-green-500 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-sky-600 uppercase tracking-wider">{{ $etiquetaIngresos }}</p>
                        <h3 class="text-3xl font-black text-green-600 mt-1">Bs. {{ number_format($totalIngresos, 2) }}</h3>
                    </div>
                    <div class="p-3 bg-green-100 rounded-full text-green-500">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                    </div>
                </div>

                <!-- Gastos -->
                <div class="bg-white rounded-2xl p-6 shadow-md border-l-8 border-red-500 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-sky-600 uppercase tracking-wider">{{ $etiquetaGastos }}</p>
                        <h3 class="text-3xl font-black text-red-600 mt-1">Bs. {{ number_format($totalEgresos, 2) }}</h3>
                    </div>
                    <div class="p-3 bg-red-100 rounded-full text-red-500">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6"></path></svg>
                    </div>
                </div>
            </div>

            <!-- Gráfico ApexCharts con Scroll Adaptable -->
            <div class="bg-white p-5 rounded-2xl shadow-xl shadow-sky-900/10 border border-sky-100 mb-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-md font-black text-blue-950 uppercase tracking-wider">Comparativo Financiero Mensual</h3>
                </div>

                <div class="w-full overflow-x-auto pb-2">
                    <div id="chartFinanzas" class="w-full"></div>
                </div>
            </div>

            <!-- QR de Pago de la OTB (Superadmin y Hacienda) -->
            @if($esAdmin)
            @php
                $estadoQr = $qr->estado();
                $badgeQr = match ($estadoQr) {
                    \App\Services\QrPago::VIGENTE => ['bg-emerald-100 text-emerald-800 border-emerald-200', '✅ Vigente'],
                    \App\Services\QrPago::POR_VENCER => ['bg-amber-100 text-amber-800 border-amber-200', '⚠️ Por vencer'],
                    \App\Services\QrPago::VENCIDO => ['bg-red-100 text-red-800 border-red-200', '⛔ Vencido'],
                    \App\Services\QrPago::SIN_FECHA => ['bg-amber-100 text-amber-800 border-amber-200', '⚠️ Sin fecha'],
                    default => ['bg-gray-100 text-gray-700 border-gray-200', 'Sin QR'],
                };
            @endphp
            <div id="qr" class="bg-white p-6 rounded-2xl shadow-md border border-sky-100 scroll-mt-6">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div>
                        <h3 class="text-lg font-black text-blue-950">QR de Pago de la OTB</h3>
                        <p class="text-xs font-bold text-sky-800">Los socios lo ven en "Mi Consumo" mientras esté vigente. Se avisa {{ \App\Services\QrPago::DIAS_AVISO }} días antes de que venza.</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-black border {{ $badgeQr[0] }}">{{ $badgeQr[1] }}</span>
                </div>

                @if($errors->qr->any())
                    <div class="mb-4 p-3 bg-red-100 border-l-4 border-red-500 rounded-r-xl text-xs font-bold text-red-800">
                        @foreach($errors->qr->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <div class="flex flex-col md:flex-row gap-6 items-start">
                    <div class="w-40 h-40 shrink-0 bg-sky-50 rounded-xl border border-sky-100 flex items-center justify-center overflow-hidden">
                        @if($qr->existe())
                            <img src="{{ $qr->url() }}" alt="QR de pago de la OTB" class="w-full h-full object-contain bg-white {{ $estadoQr === \App\Services\QrPago::VENCIDO ? 'opacity-40 grayscale' : '' }}">
                        @else
                            <span class="text-xs font-bold text-sky-700 text-center px-2">Aún no se cargó un QR</span>
                        @endif
                    </div>

                    <form action="{{ route('finanzas.qr') }}" method="POST" enctype="multipart/form-data" class="flex-1 w-full grid grid-cols-1 md:grid-cols-2 gap-4">
                        @csrf
                        <div class="md:col-span-2 text-sm font-bold text-blue-950">
                            {{ $qr->mensaje() }}
                        </div>
                        <div>
                            <label for="qr_imagen" class="block text-xs font-extrabold text-blue-950 mb-1.5">
                                {{ $qr->existe() ? 'Reemplazar imagen (opcional)' : 'Imagen del QR' }} — PNG o JPG, máx. 2 MB
                            </label>
                            <input type="file" name="qr_imagen" id="qr_imagen" accept=".png,.jpg,.jpeg" {{ $qr->existe() ? '' : 'required' }}
                                   class="w-full text-xs font-bold text-blue-950 bg-sky-50 border border-sky-200 rounded-xl p-2.5 file:mr-3 file:rounded-lg file:border-0 file:bg-blue-700 file:px-3 file:py-1.5 file:text-white file:font-bold">
                        </div>
                        <div>
                            <label for="qr_vence_el" class="block text-xs font-extrabold text-blue-950 mb-1.5">Fecha de vencimiento del QR</label>
                            <input type="date" name="qr_vence_el" id="qr_vence_el" min="{{ date('Y-m-d') }}" required
                                   value="{{ old('qr_vence_el', $qr->venceEl?->format('Y-m-d')) }}"
                                   class="w-full bg-sky-50 border border-sky-200 rounded-xl p-2.5 font-bold text-sm text-blue-950 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="md:col-span-2 flex justify-end">
                            <button type="submit" class="bg-blue-700 hover:bg-blue-800 text-white px-5 py-2.5 rounded-xl font-extrabold text-sm shadow-md transition-all">
                                Guardar QR
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            @endif

            <!-- Barra de Filtros y Botón de Excel -->
            <div class="bg-white p-4 rounded-2xl shadow-md border border-sky-100 flex flex-wrap items-center justify-between gap-4">
                <form id="formFiltros" method="GET" action="{{ route('finanzas.index') }}" class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                    <!-- Filtro Gestión -->
                    <div class="flex items-center gap-2">
                        <label for="gestion" class="text-sm font-extrabold text-blue-950 whitespace-nowrap">Gestión:</label>
                        <select name="gestion" id="gestion" onchange="aplicarFiltro(this.form)" class="bg-sky-50 border border-sky-200 text-blue-950 font-bold text-sm rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-[170px] px-4 py-2.5 cursor-pointer">
                            <option value="">Todas las gestiones</option>
                            @foreach(range(date('Y'), date('Y')-5) as $year)
                                <option value="{{ $year }}" {{ request('gestion') == $year ? 'selected' : '' }}>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Filtro Mes -->
                    <div class="flex items-center gap-2">
                        <label for="mes" class="text-sm font-extrabold text-blue-950 whitespace-nowrap">Mes:</label>
                        <select name="mes" id="mes" onchange="aplicarFiltro(this.form)" class="bg-sky-50 border border-sky-200 text-blue-950 font-bold text-sm rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 min-w-[160px] px-4 py-2.5 cursor-pointer">
                            <option value="">Todos los meses</option>
                            @foreach(\App\Support\Meses::NOMBRES as $num => $nombre)
                                <option value="{{ $num }}" {{ $mes == $num ? 'selected' : '' }}>{{ $nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
                
                <div class="flex items-center gap-3">
                    <a href="{{ route('finanzas.index') }}" onclick="limpiarFiltrosGuardados()" class="text-xs font-bold text-red-500 hover:underline {{ (request('gestion') || request('mes')) ? '' : 'hidden' }}" id="btnLimpiar">
                        Limpiar Filtros
                    </a>
                </div>
            </div>

            @if(session('success'))
            <div class="mb-6 p-4 bg-sky-100/90 border-l-4 border-sky-600 rounded-r-xl flex items-center shadow-md">
                <span class="text-blue-950 font-bold">{{ session('success') }}</span>
            </div>
            @endif

            <!-- Tabla de Balances -->
            <div class="bg-white rounded-2xl shadow-xl shadow-sky-900/10 overflow-hidden border border-sky-100">
                <div class="overflow-x-auto">
                    <table class="tabla-responsiva min-w-full divide-y divide-sky-100 table-fixed">
                        <thead class="bg-blue-900 text-white">
                            <tr>
                                <th class="w-1/6 px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Periodo</th>
                                <th class="w-1/6 px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Ingresos</th>
                                <th class="w-1/6 px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Egresos</th>
                                <th class="w-1/6 px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Saldo Final</th>
                                <th class="w-2/6 px-6 py-4 text-left text-xs font-bold uppercase tracking-wider">Detalles</th>
                                <th class="w-1/6 px-6 py-4 text-center text-xs font-bold uppercase tracking-wider">Respaldo</th>
                                @if($esAdmin)
                                <th class="w-1/6 px-6 py-4 text-right text-xs font-bold uppercase tracking-wider">Acciones</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sky-100/70 bg-white">
                            @forelse($balances as $balance)
                            <tr class="hover:bg-sky-50 transition-colors duration-150">
                                <td data-label="Periodo" class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-extrabold text-blue-950">{{ $balance->mes_nombre }}</div>
                                    <div class="text-xs text-blue-700 font-bold">Gestión {{ $balance->gestion }}</div>
                                </td>
                                <td data-label="Ingresos" class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-bold text-green-600">+ Bs. {{ number_format($balance->ingresos_totales, 2) }}</span>
                                    @if($balance->ingresos_multas > 0)
                                        <div class="text-xs font-bold text-sky-700 mt-0.5" title="Se suma automáticamente al cobrar multas">incl. Bs. {{ number_format($balance->ingresos_multas, 2) }} de multas</div>
                                    @endif
                                </td>
                                <td data-label="Egresos" class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-bold text-red-600">- Bs. {{ number_format($balance->egresos, 2) }}</span>
                                </td>
                                <td data-label="Saldo Final" class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1.5 inline-flex text-xs font-extrabold rounded-lg bg-sky-100 text-blue-950 border border-sky-200">
                                        Bs. {{ number_format($balance->saldo_final, 2) }}
                                    </span>
                                </td>
                                <td data-label="Detalles" class="px-6 py-4 max-w-xs break-all whitespace-normal">
                                    @php $detalle = $balance->detalle ?? 'Sin observaciones'; @endphp
                                    @if(strlen($detalle) > 45)
                                        <div class="text-xs text-sky-900 break-all leading-relaxed">
                                            <span class="detalle-corto block break-all">{{ Str::limit($detalle, 45, '') }}</span>
                                            <span class="detalle-completo hidden break-all">{{ $detalle }}</span>
                                            <button type="button" onclick="toggleDetalle(this)" class="text-blue-600 font-extrabold hover:underline block mt-1 focus:outline-none">
                                                ... ver más
                                            </button>
                                        </div>
                                    @else
                                        <p class="text-xs text-sky-900 break-all leading-relaxed">{{ $detalle }}</p>
                                    @endif
                                </td>
                                <td data-label="Respaldo" class="px-6 py-4 whitespace-nowrap text-center">
                                    @if($balance->comprobante_url)
                                        <div class="flex items-center justify-center space-x-2">
                                            <a href="{{ route('finanzas.comprobante', $balance) }}" target="_blank" title="Ver documento"
                                               class="inline-flex items-center px-2.5 py-1 bg-sky-100 text-sky-800 rounded-lg font-bold text-xs hover:bg-sky-200 transition-all border border-sky-200">
                                                👁️ Ver
                                            </a>
                                            <a href="{{ route('finanzas.comprobante', [$balance, 'descargar' => 1]) }}" title="Descargar documento"
                                               class="inline-flex items-center px-2.5 py-1 bg-emerald-100 text-emerald-800 rounded-lg font-bold text-xs hover:bg-emerald-200 transition-all border border-emerald-200">
                                                📥 Descargar
                                            </a>
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400 italic">Sin respaldo</span>
                                    @endif
                                </td>
                                @if($esAdmin)
                                <td data-label="Acciones" class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <a href="{{ route('finanzas.edit', $balance->id) }}" class="inline-flex items-center px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-xs font-extrabold shadow-sm transition-all hover:scale-105">
                                            Editar
                                        </a>

                                        @if($esSuperAdmin)
                                        <form action="{{ route('finanzas.destroy', $balance->id) }}" method="POST" class="m-0 p-0">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white rounded-lg text-xs font-extrabold shadow-sm transition-all hover:scale-105" onclick="return confirm('¿Eliminar este balance?')">
                                                Eliminar
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                                @endif
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ $esAdmin ? '7' : '6' }}" class="px-6 py-12 text-center text-sky-700 font-extrabold text-base">
                                    <div class="flex flex-col items-center justify-center gap-2">
                                        <svg class="w-10 h-10 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        <span>Sin registros para la búsqueda seleccionada</span>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- Scripts -->
    <script>
        function toggleDetalle(btn) {
            const container = btn.parentElement;
            const corto = container.querySelector('.detalle-corto');
            const completo = container.querySelector('.detalle-completo');

            if (completo.classList.contains('hidden')) {
                completo.classList.remove('hidden');
                corto.classList.add('hidden');
                btn.textContent = ' ver menos';
            } else {
                completo.classList.add('hidden');
                corto.classList.remove('hidden');
                btn.textContent = '... ver más';
            }
        }

        // --- MANEJO DE MEMORIA DE FILTROS ---
        function aplicarFiltro(form) {
            const gestion = form.gestion.value;
            const mes = form.mes.value;
            
            if (gestion) sessionStorage.setItem('finanzas_gestion', gestion);
            else sessionStorage.removeItem('finanzas_gestion');

            if (mes) sessionStorage.setItem('finanzas_mes', mes);
            else sessionStorage.removeItem('finanzas_mes');

            form.submit();
        }

        function limpiarFiltrosGuardados() {
            sessionStorage.removeItem('finanzas_gestion');
            sessionStorage.removeItem('finanzas_mes');
        }

        document.addEventListener("DOMContentLoaded", function () {
            // Verificar si hay parámetros de búsqueda en la URL
            const urlParams = new URLSearchParams(window.location.search);
            const tieneGestionParam = urlParams.has('gestion');
            const tieneMesParam = urlParams.has('mes');

            const gestionGuardada = sessionStorage.getItem('finanzas_gestion');
            const mesGuardado = sessionStorage.getItem('finanzas_mes');

            // Si se volvió tras una acción sin params de URL, pero existe filtro guardado:
            if (!tieneGestionParam && !tieneMesParam && (gestionGuardada || mesGuardado)) {
                let url = new URL(window.location.href);
                if (gestionGuardada) url.searchParams.set('gestion', gestionGuardada);
                if (mesGuardado) url.searchParams.set('mes', mesGuardado);
                window.location.href = url.toString();
                return;
            }

            // --- APEXCHARTS CONFIG ---
            const totalDatos = @json(count($grafico['meses']));
            const chartElement = document.querySelector("#chartFinanzas");

            if (totalDatos > 12) {
                chartElement.style.width = (totalDatos * 80) + "px";
            } else {
                chartElement.style.width = "100%";
            }

            var options = {
                series: [{
                    name: 'Ingresos (Bs.)',
                    data: @json($grafico['ingresos'])
                }, {
                    name: 'Gastos (Bs.)',
                    data: @json($grafico['egresos'])
                }],
                chart: {
                    type: 'bar',
                    height: 330,
                    toolbar: { show: false }
                },
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: totalDatos > 12 ? '75%' : (totalDatos <= 3 ? '35%' : '60%'),
                        maxColumnWidth: totalDatos <= 12 ? 60 : 45,
                        borderRadius: 4
                    },
                },
                dataLabels: { enabled: false },
                stroke: { show: true, width: 2, colors: ['transparent'] },
                colors: ['#10B981', '#EF4444'],
                xaxis: {
                    categories: @json($grafico['meses']),
                    labels: {
                        rotate: totalDatos > 12 ? -45 : 0,
                        style: { fontSize: '11px', fontWeight: 600 }
                    }
                },
                yaxis: {
                    title: { text: 'Monto en Bs.', style: { fontSize: '11px', fontWeight: 600 } },
                    labels: {
                        formatter: function (val) {
                            return val.toLocaleString();
                        }
                    }
                },
                fill: { opacity: 1 },
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return "Bs. " + val.toFixed(2)
                        }
                    }
                }
            };

            var chart = new ApexCharts(chartElement, options);
            chart.render();
        });
    </script>
</x-app-layout>