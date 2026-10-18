@php
    use App\Http\Controllers\PlanillaLecturasController as Planilla;
    use App\Models\Lectura;
    use App\Support\Meses;

    $periodo = Meses::nombre($mes).' '.$gestion;
    $pendientes = $filas->count() - $registradas;
    $input = 'w-full sm:w-32 px-3 py-2 rounded-xl border text-right font-black text-blue-950 focus:outline-none focus:ring-2 focus:ring-blue-500/50';
    $selector = 'w-full px-4 py-2.5 bg-white rounded-xl border border-sky-300 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500/50 text-sm font-semibold text-blue-950 shadow-sm cursor-pointer';
@endphp

<x-app-layout>
    <div class="py-10 bg-sky-200/70 min-h-screen">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">

            <div class="md:flex md:items-end md:justify-between gap-4">
                <div>
                    @if($esGestion)
                        <a href="{{ route('lecturas.index') }}" class="text-xs font-bold text-blue-700 hover:underline">← Volver a Lecturas</a>
                    @endif
                    <h2 class="font-extrabold text-3xl text-blue-950 leading-tight">Carga rápida – {{ $periodo }}</h2>
                    <p class="text-sm text-sky-900 mt-1 font-bold">
                        Anota la lectura del medidor de cada socio. La anterior la completa el sistema. Deja vacío a quien todavía no visitaste.
                    </p>
                </div>
                <form method="GET" action="{{ route('lecturas.planilla') }}" class="mt-4 md:mt-0 grid grid-cols-2 gap-3 min-w-[16rem]">
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
            <x-errores-formulario />

            <div class="grid grid-cols-3 gap-3">
                <div class="bg-white rounded-2xl p-4 shadow border-l-8 border-emerald-500">
                    <p class="text-xs font-black uppercase text-sky-700">Registradas</p>
                    <p class="text-2xl font-black text-emerald-700">{{ $registradas }} <span class="text-sm text-sky-700">de {{ $filas->count() }}</span></p>
                </div>
                <div class="bg-white rounded-2xl p-4 shadow border-l-8 border-amber-500">
                    <p class="text-xs font-black uppercase text-sky-700">Faltan</p>
                    <p class="text-2xl font-black text-amber-600">{{ $pendientes }}</p>
                </div>
                <div class="bg-white rounded-2xl p-4 shadow border-l-8 border-blue-600">
                    <p class="text-xs font-black uppercase text-sky-700">Tarifa</p>
                    <p class="text-2xl font-black text-blue-950">Bs. {{ number_format($tarifa, 2, ',', '.') }}<span class="text-sm text-sky-700">/m³</span></p>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-md border border-sky-100 p-4 flex flex-col sm:flex-row gap-3 sm:items-center">
                <input type="search" id="buscar" placeholder="🔍 Buscar socio por nombre o C.I."
                       class="flex-1 px-4 py-2.5 rounded-xl border border-sky-300 text-sm font-semibold text-blue-950 focus:outline-none focus:ring-2 focus:ring-blue-500/50">
                @if($zonas->isNotEmpty())
                    <select id="zona" class="px-4 py-2.5 rounded-xl border border-sky-300 text-sm font-semibold text-blue-950 bg-white" aria-label="Zona">
                        <option value="">📍 Todas las zonas</option>
                        @foreach($zonas as $zona)
                            <option value="{{ $zona }}">{{ $zona }}</option>
                        @endforeach
                        <option value="__sin">Sin zona</option>
                    </select>
                @endif
                <label class="inline-flex items-center gap-2 text-sm font-bold text-blue-950 cursor-pointer">
                    <input type="checkbox" id="solo_faltan" class="rounded border-sky-400 text-blue-600" @checked($pendientes > 0)>
                    Mostrar solo los que faltan
                </label>
            </div>

            <form method="POST" action="{{ route('lecturas.planilla.store') }}" id="planilla">
                @csrf
                <input type="hidden" name="mes" value="{{ $mes }}">
                <input type="hidden" name="gestion" value="{{ $gestion }}">

                <div class="bg-white rounded-2xl shadow-xl border border-sky-100 overflow-hidden">
                    <table class="tabla-responsiva min-w-full divide-y divide-sky-100 text-sm">
                        <thead class="bg-blue-900 text-white text-xs font-bold uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3 text-left">Socio</th>
                                <th class="px-4 py-3 text-right">Lectura anterior</th>
                                <th class="px-4 py-3 text-right">Lectura actual</th>
                                <th class="px-4 py-3 text-right">Consumo · Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sky-100 text-blue-950">
                            @php $zonaAnterior = false; @endphp
                            @forelse($filas as $fila)
                                @if($zonas->isNotEmpty() && $fila->socio->zona !== $zonaAnterior)
                                    @php $zonaAnterior = $fila->socio->zona; @endphp
                                    <tr class="encabezado-zona bg-sky-100" data-zona="{{ $fila->socio->zona ?? '__sin' }}">
                                        <td colspan="4" class="px-4 py-2 text-xs font-black uppercase tracking-wider text-blue-900">📍 {{ $fila->socio->zona ?? 'Sin zona' }}</td>
                                    </tr>
                                @endif
                                @php
                                    $id = $fila->socio->id;
                                    $errorFila = $errors->first("lecturas.{$id}.actual") ?: $errors->first("lecturas.{$id}.anterior");
                                @endphp
                                <tr class="fila {{ $fila->registrada ? 'bg-emerald-50/60' : '' }} {{ $errorFila ? 'bg-red-50' : '' }}"
                                    data-buscar="{{ mb_strtolower($fila->socio->name.' '.$fila->socio->ci) }}"
                                    data-zona="{{ $fila->socio->zona ?? '__sin' }}"
                                    data-registrada="{{ $fila->registrada ? 1 : 0 }}">
                                    <td data-label="Socio" class="px-4 py-3">
                                        <div class="font-extrabold">{{ $fila->socio->name }}</div>
                                        <div class="text-xs font-bold text-sky-700">
                                            @if($fila->socio->orden_recorrido)<span class="font-black">N.º {{ $fila->socio->orden_recorrido }}</span> · @endif
                                            C.I. {{ $fila->socio->ci ?? 'S/N' }}
                                        </div>
                                        @if($errorFila)
                                            <div class="mt-1 text-xs font-bold text-red-700">{{ $errorFila }}</div>
                                        @endif
                                    </td>

                                    @if($fila->registrada)
                                        <td data-label="Lectura anterior" class="px-4 py-3 text-right font-bold text-sky-800">{{ Lectura::m3($fila->registrada->lectura_anterior) }}</td>
                                        <td data-label="Lectura actual" class="px-4 py-3 text-right">
                                            <span class="font-black">{{ Lectura::m3($fila->registrada->lectura_actual) }}</span>
                                            @if($esGestion)
                                                <a href="{{ route('lecturas.edit', $fila->registrada->id) }}" class="ml-2 text-xs font-extrabold text-blue-700 hover:underline">Editar</a>
                                            @endif
                                        </td>
                                        <td data-label="Consumo · Monto" class="px-4 py-3 text-right">
                                            <span class="px-2.5 py-1 rounded-full text-xs font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                ✅ Registrada · {{ Lectura::m3($fila->registrada->consumo) }}
                                            </span>
                                        </td>
                                    @else
                                        <td data-label="Lectura anterior" class="px-4 py-3 text-right">
                                            @if($fila->anterior !== null)
                                                <span class="font-bold text-sky-800" title="Lectura de {{ $fila->periodo_anterior }}">{{ Lectura::m3($fila->anterior) }}</span>
                                                <input type="hidden" class="anterior" value="{{ $fila->anterior }}">
                                            @else
                                                <input type="number" step="any" min="0" inputmode="decimal" name="lecturas[{{ $id }}][anterior]"
                                                       value="{{ old("lecturas.{$id}.anterior") }}" placeholder="Inicial"
                                                       class="anterior {{ $input }} border-amber-300 bg-amber-50" title="Primera lectura: lectura inicial del medidor">
                                            @endif
                                        </td>
                                        <td data-label="Lectura actual" class="px-4 py-3 text-right">
                                            <input type="number" step="any" min="0" inputmode="decimal" name="lecturas[{{ $id }}][actual]"
                                                   value="{{ old("lecturas.{$id}.actual") }}" placeholder="m³"
                                                   data-promedio="{{ $fila->promedio ?? '' }}"
                                                   data-max="{{ $fila->siguiente?->lectura_actual ?? '' }}"
                                                   class="actual {{ $input }} {{ $errorFila ? 'border-red-500' : 'border-sky-300' }}">
                                        </td>
                                        <td data-label="Consumo · Monto" class="px-4 py-3 text-right">
                                            <span class="vista-previa text-xs font-bold text-sky-700">—</span>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-6 py-10 text-center font-bold text-sky-700">No hay socios registrados.</td></tr>
                            @endforelse
                            <tr id="sin-coincidencias" class="hidden">
                                <td colspan="4" class="px-6 py-8 text-center font-bold text-sky-700">Ningún socio coincide con la búsqueda.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="sticky bottom-0 mt-4 bg-white/95 backdrop-blur rounded-2xl shadow-xl border border-sky-100 p-4 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
                    <p class="text-sm font-bold text-blue-950">
                        Lecturas escritas: <span id="contador" class="font-black">0</span>
                        · Total a cobrar: <span id="total" class="font-black text-emerald-700">Bs. 0,00</span>
                        <span id="alertas" class="block text-xs text-amber-700"></span>
                    </p>
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3 rounded-xl font-extrabold text-sm shadow-md">
                        💾 Guardar lecturas de {{ $periodo }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const tarifa = {{ $tarifa }};
            const vecesAnormal = {{ Planilla::VECES_PROMEDIO_ANORMAL }};
            const minimoAnormal = {{ Planilla::MINIMO_ANORMAL_M3 }};
            const filas = [...document.querySelectorAll('tr.fila')];
            const actuales = [...document.querySelectorAll('input.actual')];
            const bs = n => 'Bs. ' + n.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const m3 = n => n.toLocaleString('es-BO', { maximumFractionDigits: 2 }) + ' m³';

            // Calcula consumo y monto de una fila y marca lo que no cuadra
            function revisar(fila) {
                const actual = fila.querySelector('input.actual');
                const previa = fila.querySelector('.vista-previa');
                if (!actual) return { escrita: false };

                const anterior = parseFloat(fila.querySelector('.anterior')?.value);
                const valor = parseFloat(actual.value);
                actual.classList.remove('border-red-500', 'border-amber-500', 'bg-amber-50');

                if (actual.value === '') { previa.textContent = '—'; previa.className = 'vista-previa text-xs font-bold text-sky-700'; return { escrita: false }; }
                if (isNaN(anterior)) { previa.textContent = 'Falta la lectura inicial'; previa.className = 'vista-previa text-xs font-bold text-amber-700'; return { escrita: true, error: true }; }

                const consumo = valor - anterior;
                const max = parseFloat(actual.dataset.max);
                if (isNaN(valor) || consumo < 0) {
                    actual.classList.add('border-red-500');
                    previa.textContent = '❌ Menor que la anterior';
                    previa.className = 'vista-previa text-xs font-bold text-red-700';
                    return { escrita: true, error: true };
                }
                if (!isNaN(max) && valor > max) {
                    actual.classList.add('border-red-500');
                    previa.textContent = '❌ Supera la del mes siguiente (' + m3(max) + ')';
                    previa.className = 'vista-previa text-xs font-bold text-red-700';
                    return { escrita: true, error: true };
                }

                const promedio = parseFloat(actual.dataset.promedio);
                const anormal = !isNaN(promedio) && promedio > 0 && consumo > promedio * vecesAnormal && consumo > minimoAnormal;
                const monto = Math.round(consumo * tarifa * 100) / 100;
                previa.textContent = (anormal ? '⚠️ ' : '') + m3(consumo) + ' · ' + bs(monto) + (anormal ? ' (promedio ' + m3(promedio) + ')' : '');
                previa.className = 'vista-previa text-xs font-black ' + (anormal ? 'text-amber-700' : 'text-emerald-700');
                if (anormal) actual.classList.add('border-amber-500', 'bg-amber-50');

                return { escrita: true, monto, anormal, nombre: fila.querySelector('.font-extrabold').textContent.trim() };
            }

            function resumen() {
                let cantidad = 0, total = 0;
                const anormales = [], errores = [];
                filas.forEach(fila => {
                    const r = revisar(fila);
                    if (!r.escrita) return;
                    cantidad++;
                    if (r.error) errores.push(fila);
                    else total += r.monto;
                    if (r.anormal) anormales.push(r.nombre);
                });
                document.getElementById('contador').textContent = cantidad;
                document.getElementById('total').textContent = bs(total);
                document.getElementById('alertas').textContent = anormales.length
                    ? '⚠️ Consumo muy alto (más de ' + vecesAnormal + ' veces su promedio): ' + anormales.join(', ') + '. Revisa si hay fuga o error al anotar.'
                    : '';
                return { cantidad, anormales, errores };
            }

            document.getElementById('planilla').addEventListener('input', resumen);

            // Enter pasa al medidor del siguiente socio visible (más rápido al cargar en fila)
            actuales.forEach(campo => campo.addEventListener('keydown', e => {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                const visibles = actuales.filter(c => c.closest('tr').style.display !== 'none');
                const siguiente = visibles[visibles.indexOf(campo) + 1];
                if (siguiente) siguiente.focus(); else campo.blur();
            }));

            document.getElementById('planilla').addEventListener('submit', e => {
                const r = resumen();
                if (r.cantidad === 0) { e.preventDefault(); alert('No escribiste ninguna lectura.'); return; }
                if (r.errores.length) { e.preventDefault(); r.errores[0].querySelector('input.actual, input.anterior')?.focus(); alert('Hay lecturas con errores (marcadas en rojo). Corrígelas antes de guardar.'); return; }
                const aviso = r.anormales.length ? '\n\n⚠️ Consumo muy alto en: ' + r.anormales.join(', ') + '.' : '';
                if (!confirm('¿Guardar ' + r.cantidad + ' lectura(s)?' + aviso)) e.preventDefault();
            });

            // Búsqueda y "solo los que faltan"
            const buscar = document.getElementById('buscar');
            const soloFaltan = document.getElementById('solo_faltan');
            const zona = document.getElementById('zona');
            const encabezados = [...document.querySelectorAll('tr.encabezado-zona')];
            function filtrar() {
                const termino = buscar.value.toLowerCase().trim();
                const zonaElegida = zona ? zona.value : '';
                let visibles = 0;
                const zonasVisibles = new Set();
                filas.forEach(fila => {
                    const ver = (!termino || fila.dataset.buscar.includes(termino))
                        && (!zonaElegida || fila.dataset.zona === zonaElegida)
                        && !(soloFaltan.checked && fila.dataset.registrada === '1');
                    fila.style.display = ver ? '' : 'none';
                    if (ver) { visibles++; zonasVisibles.add(fila.dataset.zona); }
                });
                // El título de cada zona solo se muestra si tiene socios visibles
                encabezados.forEach(e => e.style.display = zonasVisibles.has(e.dataset.zona) ? '' : 'none');
                document.getElementById('sin-coincidencias').classList.toggle('hidden', visibles > 0 || filas.length === 0);
            }
            buscar.addEventListener('input', filtrar);
            soloFaltan.addEventListener('change', filtrar);
            if (zona) zona.addEventListener('change', filtrar);

            filtrar();
            resumen();
        })();
    </script>
</x-app-layout>
