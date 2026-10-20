@php
    use App\Models\Lectura;
    use App\Support\Meses;
    use App\Support\WhatsApp;

    $periodo = Meses::nombre($mes).' '.$gestion;
    $bs = fn ($monto) => 'Bs. '.number_format((float) $monto, 2, ',', '.');
    $selector = 'w-full px-4 py-2.5 bg-white rounded-xl border border-sky-300 text-sm font-semibold text-blue-950 shadow-sm cursor-pointer';
@endphp

<x-app-layout>
    <div class="py-10 bg-sky-200/70 min-h-screen">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            <div class="md:flex md:items-end md:justify-between gap-4">
                <div>
                    <a href="{{ route('lecturas.index') }}" class="text-xs font-bold text-blue-700 hover:underline">← Volver a Lecturas</a>
                    <h2 class="font-extrabold text-3xl text-blue-950 leading-tight">📲 Avisar lecturas – {{ $periodo }}</h2>
                    <p class="text-sm text-sky-900 mt-1 font-bold">
                        Cada botón abre WhatsApp con el mensaje ya escrito (lectura, consumo, monto y cómo pagar): solo toca Enviar.
                    </p>
                </div>
                <form method="GET" action="{{ route('lecturas.avisos') }}" class="mt-4 md:mt-0 grid grid-cols-2 gap-3 min-w-[16rem]">
                    <select name="mes" onchange="this.form.submit()" class="{{ $selector }}" aria-label="Mes">
                        @foreach(Meses::NOMBRES as $num => $nombre)
                            <option value="{{ $num }}" @selected($num === $mes)>{{ $nombre }}</option>
                        @endforeach
                    </select>
                    <select name="gestion" onchange="this.form.submit()" class="{{ $selector }}" aria-label="Gestión">
                        @foreach($gestiones as $g)
                            <option value="{{ $g }}" @selected($g === $gestion)>{{ $g }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <x-mensajes-sesion />

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach([
                    ['Por avisar', $resumen['por_avisar'], 'border-amber-500 text-amber-600'],
                    ['Avisados', $resumen['avisados'], 'border-emerald-500 text-emerald-700'],
                    ['Sin celular', $resumen['sin_celular'], 'border-gray-400 text-gray-600'],
                    ['Ya pagadas', $resumen['pagadas'], 'border-blue-600 text-blue-900'],
                ] as [$titulo, $valor, $colores])
                    <div class="bg-white rounded-2xl px-3 py-3 sm:p-4 shadow border-l-4 {{ explode(' ', $colores)[0] }}">
                        <p class="text-[0.65rem] sm:text-xs font-black uppercase text-sky-700">{{ $titulo }}</p>
                        <p class="text-xl sm:text-2xl font-black {{ explode(' ', $colores)[1] }}">{{ $valor }}</p>
                    </div>
                @endforeach
            </div>

            <div class="bg-white rounded-2xl shadow-xl border border-sky-100 overflow-hidden">
                <table class="tabla-responsiva min-w-full divide-y divide-sky-100 text-sm">
                    <thead class="bg-blue-900 text-white text-xs font-bold uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3 text-left">Socio</th>
                            <th class="px-4 py-3 text-right">Consumo</th>
                            <th class="px-4 py-3 text-right">Monto</th>
                            <th class="px-4 py-3 text-center">Aviso</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sky-100 text-blue-950">
                        @forelse($lecturas as $lectura)
                            @php
                                $socio = $lectura->usuario;
                                $celular = WhatsApp::numero($socio?->telefono);
                            @endphp
                            <tr class="fila-aviso">
                                <td data-label="Socio" class="px-4 py-3">
                                    <div class="font-extrabold">{{ $socio?->name ?? '—' }}</div>
                                    <div class="text-xs font-bold text-sky-700">
                                        {{ $socio?->zona ? '📍 '.$socio->zona.' · ' : '' }}{{ $socio?->telefono ? '📱 '.$socio->telefono : 'Sin celular' }}
                                    </div>
                                </td>
                                <td data-label="Consumo" class="px-4 py-3 text-right font-bold">{{ Lectura::m3($lectura->consumo) }}</td>
                                <td data-label="Monto" class="px-4 py-3 text-right font-black text-emerald-700">{{ $bs($lectura->monto) }}</td>
                                <td data-label="Aviso" class="px-4 py-3 text-center">
                                    @if($lectura->estado === Lectura::PAGADO)
                                        <span class="px-2.5 py-1 rounded-full text-xs font-extrabold bg-blue-100 text-blue-900 border border-blue-200">💳 Ya pagó</span>
                                    @elseif(! $celular)
                                        <span class="px-2.5 py-1 rounded-full text-xs font-extrabold bg-gray-100 text-gray-700 border border-gray-200" title="Registra su celular en Socios o avísale en persona">📵 Avisar en persona</span>
                                    @else
                                        <div class="estado-aviso inline-flex flex-wrap items-center justify-center gap-2">
                                            @if($lectura->avisado_at)
                                                <span class="text-xs font-extrabold text-emerald-700" title="{{ $lectura->avisado_at->format('d/m/Y H:i') }}">✅ Avisado {{ $lectura->avisado_at->format('d/m') }}</span>
                                            @endif
                                            <a href="{{ route('lecturas.avisar', $lectura) }}" target="_blank" rel="noopener"
                                               class="boton-avisar inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-extrabold shadow-sm transition-all
                                                      {{ $lectura->avisado_at ? 'bg-white border border-emerald-300 text-emerald-800 hover:bg-emerald-50' : 'bg-emerald-600 hover:bg-emerald-700 text-white' }}">
                                                📲 {{ $lectura->avisado_at ? 'Reenviar' : 'Avisar por WhatsApp' }}
                                            </a>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-10 text-center font-bold text-sky-700">
                                    Todavía no hay lecturas registradas para {{ $periodo }}.
                                    <a href="{{ route('lecturas.planilla', ['mes' => $mes, 'gestion' => $gestion]) }}" class="underline text-blue-700">Cargar lecturas</a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="text-xs font-bold text-sky-900">
                ℹ️ El mensaje se envía desde el WhatsApp de este dispositivo. Al tocar "Avisar" el socio queda marcado como avisado y se registra en el Historial.
            </p>
        </div>
    </div>

    <script>
        // Al tocar "Avisar" (se abre WhatsApp en otra pestaña) la fila queda marcada al instante
        document.querySelectorAll('.boton-avisar').forEach(boton => boton.addEventListener('click', () => {
            const estado = boton.closest('.estado-aviso');
            if (!estado.querySelector('.ya-avisado')) {
                const marca = document.createElement('span');
                marca.className = 'ya-avisado text-xs font-extrabold text-emerald-700';
                marca.textContent = '✅ Avisado ahora';
                estado.prepend(marca);
            }
            boton.className = 'boton-avisar inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-extrabold shadow-sm bg-white border border-emerald-300 text-emerald-800 hover:bg-emerald-50';
            boton.textContent = '📲 Reenviar';
        }));
    </script>
</x-app-layout>
