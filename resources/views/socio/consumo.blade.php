<x-app-layout>
    <x-slot name="header">
        <h2 class="font-extrabold text-xl text-blue-950 leading-tight">
            👋 ¡Bienvenido! Este es tu Historial de Consumo
        </h2>
    </x-slot>

    <div class="py-12 min-h-screen" style="background-color: #bae6fd !important;" x-data="{ openQr: false }">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- ALERTA DE ÉXITO -->
            @if(session('success'))
                <div class="bg-emerald-100 border border-emerald-300 text-emerald-800 px-4 py-3 rounded-xl font-bold text-center">
                    ✅ {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-xl shadow-sky-950/10 rounded-2xl p-6 border border-sky-100">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <h3 class="text-lg font-black text-blue-950 tracking-tight">Mis Lecturas de Agua de la OTB</h3>
                    <a href="{{ route('socio.pagos') }}" class="text-sm font-extrabold text-blue-700 hover:underline">🧾 Ver mi historial de pagos →</a>
                </div>

                <div class="overflow-x-auto border border-sky-100 rounded-xl shadow-inner">
                    <table class="tabla-responsiva min-w-full divide-y divide-sky-100">
                        <thead class="bg-blue-900 text-white text-xs font-bold uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-3 text-left">Mes / Gestión</th>
                                <th class="px-6 py-3 text-center">Lectura Anterior</th>
                                <th class="px-6 py-3 text-center">Lectura Actual</th>
                                <th class="px-6 py-3 text-center">Tu Consumo</th>
                                <th class="px-6 py-3 text-left">Total a Pagar</th>
                                <th class="px-6 py-3 text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-sky-100/70 text-sm">
                            @forelse($misLecturas as $lectura)
                            <tr class="hover:bg-sky-50 transition-colors duration-150">
                                <td data-label="Mes / Gestión" class="px-6 py-4 whitespace-nowrap text-blue-900 font-extrabold">
                                    {{ $lectura->periodo }}
                                </td>
                                <td data-label="Lectura Anterior" class="px-6 py-4 whitespace-nowrap text-center text-sky-700 font-semibold">
                                    {{ $lectura->lectura_anterior }} m³
                                </td>
                                <td data-label="Lectura Actual" class="px-6 py-4 whitespace-nowrap text-center text-blue-950 font-black">
                                    {{ $lectura->lectura_actual }} m³
                                </td>
                                <td data-label="Tu Consumo" class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2.5 py-1 text-xs font-extrabold rounded-lg bg-sky-100 text-blue-950 border border-sky-200">
                                        {{ $lectura->consumo }} m³
                                    </span>
                                </td>
                                <td data-label="Total a Pagar" class="px-6 py-4 whitespace-nowrap text-emerald-600 font-extrabold">
                                    Bs. {{ number_format($lectura->monto, 2) }}
                                </td>
                                <td data-label="Estado" class="px-6 py-4 whitespace-nowrap text-center">
                                    @if($lectura->estado == 'pendiente')
                                        <span class="px-3 py-1 inline-flex text-xs font-extrabold rounded-full bg-red-100 text-red-800 border border-red-200 items-center animate-pulse">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-1.5"></span>
                                            🛑 Pendiente
                                        </span>
                                    @else
                                        <span class="px-3 py-1 inline-flex text-xs font-extrabold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200 items-center">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                                            ✅ Pagado
                                        </span>
                                        @if($lectura->metodo_pago)
                                            <div class="mt-1 text-xs font-bold text-sky-800">{{ $lectura->metodo_pago->icono() }} {{ $lectura->metodo_pago->etiqueta() }}</div>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-sky-600 font-bold">
                                    Aún no tienes lecturas registradas.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- MODULO VISOR QR PARA SOCIOS -->
            <div class="bg-white border border-sky-100 rounded-2xl p-6 shadow-xl shadow-sky-950/10">
                <div class="flex justify-center">
                    <button @click="openQr = !openQr" 
                            class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-blue-950 font-black py-3 px-6 rounded-xl text-sm shadow-md shadow-amber-500/20 transition-all hover:scale-105 active:scale-95">
                        🖼️ Mostrar Código QR
                    </button>
                </div>

                <div x-show="openQr" x-transition class="mt-6 border-t border-sky-100 pt-6 flex flex-col items-center text-center">
                    
                    <!-- VISTA DE IMAGEN QR UNIFICADA (SUBIDA DESDE FINANZAS POR SUPERADMIN) -->
                    <div class="bg-sky-50 p-4 rounded-2xl shadow-inner border border-sky-100">
                        @if($qr->disponible())
                            <img src="{{ $qr->url() }}" alt="QR OTB" class="w-56 h-56 mx-auto rounded-xl shadow-md object-contain bg-white">
                            @if($qr->venceEl)
                                <p class="mt-3 text-xs font-extrabold text-blue-900">Válido hasta el {{ $qr->venceEl->format('d/m/Y') }}</p>
                            @endif
                        @else
                            {{-- Nunca mostrar un QR vencido o de prueba: el pago podría perderse --}}
                            <div class="w-56 h-56 mx-auto rounded-xl bg-white border-2 border-dashed border-red-300 flex flex-col items-center justify-center p-4">
                                <span class="text-3xl">⛔</span>
                                <p class="mt-2 text-sm font-black text-red-700">QR de pago no disponible</p>
                                <p class="mt-1 text-xs font-bold text-gray-600">Comunícate con el Secretario de Finanzas para pagar.</p>
                            </div>
                        @endif
                    </div>

                    <div class="mt-4 max-w-md w-full">
                        <p class="text-xs font-black text-blue-900 uppercase tracking-wider mb-3">Instrucciones de Validación Asincrónica</p>
                        
                        <div class="mb-4 p-3.5 bg-amber-50 border-l-4 border-amber-500 rounded-r-xl flex items-start shadow-sm text-left">
                            <span class="text-xl mr-2">⚠️</span>
                            <p class="text-xs text-amber-900 font-bold leading-relaxed">
                                <span class="text-amber-950 uppercase block mb-0.5">¡Atención Socio!</span>
                                Antes de proceder, revisa bien que tus datos personales (Nombre Completo y C.I.) estén correctamente registrados en el sistema. Si notas que falta algo o ves un error, comunícate con la administración para corregirlo.
                            </p>
                        </div>

                        <ol class="text-sm text-sky-950 text-left list-decimal list-inside space-y-2 font-medium leading-relaxed">
                            <li>Abre la aplicación de tu banco en tu celular.</li>
                            <li>
                                Escanea este QR e ingresa el monto correspondiente a tu deuda.
                                <span class="text-xs text-blue-800 block font-bold mt-0.5 ml-4">💡 Puedes pagar un solo mes, varios meses acumulados o el total de tu deuda en una sola transferencia.</span>
                            </li>
                            <li>
                                Comparte el comprobante de pago por WhatsApp al Secretario de Finanzas 
                                (<a href="{{ \App\Support\WhatsApp::enlace(config('otb.telefono_finanzas')) }}" target="_blank" rel="noopener" class="font-extrabold text-blue-700 underline hover:text-blue-900">{{ config('otb.telefono_finanzas') }}</a>) 
                                indicando tu nombre completo, carnet <strong class="text-red-600 font-extrabold uppercase"> detallando exactamente qué meses estás cancelando</strong> para dar de baja tu deuda en el sistema.
                            </li>
                        </ol>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>