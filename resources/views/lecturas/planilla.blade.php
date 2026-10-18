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

            {{-- Estado del envío: lecturas guardadas en el celular sin señal, sesión vencida, etc. --}}
            <div id="estado-envio" class="hidden p-4 rounded-r-xl border-l-4 shadow-md text-sm font-bold" role="status">
                <span id="estado-texto"></span>
                <button type="button" id="enviar-ahora" class="hidden ml-2 underline font-black">Enviar ahora</button>
                <a id="ir-login" href="{{ route('login') }}" class="hidden ml-2 underline font-black">Ingresar de nuevo</a>
            </div>

            {{-- Instalar en el celular (Android/Chrome muestra el botón; en iPhone se explica cómo) --}}
            <div id="instalar-app" class="hidden p-4 bg-white rounded-2xl shadow border border-sky-100 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
                <p class="text-sm font-bold text-blue-950">📲 Instala H2O Control en tu celular: se abre como una app y funciona sin señal.</p>
                <button type="button" id="instalar" class="hidden bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded-xl font-extrabold text-sm shadow">Instalar</button>
                <p id="instalar-iphone" class="hidden text-xs font-bold text-sky-800">En iPhone: toca Compartir ⬆️ y luego "Agregar a inicio".</p>
            </div>
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
                                    $errorFila = $errors->first("lecturas.{$id}.actual") ?: $errors->first("lecturas.{$id}.anterior") ?: $errors->first("lecturas.{$id}.observacion");
                                @endphp
                                <tr class="fila {{ $fila->registrada ? 'bg-emerald-50/60' : '' }} {{ $errorFila ? 'bg-red-50' : '' }}" data-id="{{ $id }}"
                                    data-buscar="{{ mb_strtolower($fila->socio->name.' '.$fila->socio->ci) }}"
                                    data-zona="{{ $fila->socio->zona ?? '__sin' }}"
                                    data-registrada="{{ $fila->registrada ? 1 : 0 }}">
                                    <td data-label="Socio" class="px-4 py-3">
                                        <div class="font-extrabold">{{ $fila->socio->name }}</div>
                                        <div class="text-xs font-bold text-sky-700">
                                            @if($fila->socio->orden_recorrido)<span class="font-black">N.º {{ $fila->socio->orden_recorrido }}</span> · @endif
                                            C.I. {{ $fila->socio->ci ?? 'S/N' }}
                                        </div>
                                        <div class="error-fila mt-1 text-xs font-bold text-red-700 {{ $errorFila ? '' : 'hidden' }}">{{ $errorFila }}</div>
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
                                            @if($fila->registrada->foto)
                                                <div class="mt-1 text-xs font-bold text-sky-800">📷 Con foto del medidor</div>
                                            @endif
                                            @if($fila->registrada->observacion)
                                                <div class="mt-1 text-xs font-bold text-amber-800">📝 {{ $fila->registrada->observacion }}</div>
                                            @endif
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
                                            <div class="foto-medidor mt-2 flex items-center justify-end gap-2">
                                                <img class="miniatura hidden w-12 h-12 object-cover rounded-lg border border-sky-300" alt="Foto del medidor">
                                                <button type="button" class="quitar-foto hidden text-xs font-black text-red-700" title="Quitar foto">✕</button>
                                                <label class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-sky-100 hover:bg-sky-200 text-blue-900 text-xs font-extrabold cursor-pointer border border-sky-200">
                                                    📷 <span class="texto-foto">Foto</span>
                                                    <input type="file" accept="image/*" capture="environment" class="entrada-foto sr-only">
                                                </label>
                                            </div>
                                        </td>
                                        <td data-label="Consumo · Monto" class="px-4 py-3 text-right">
                                            <span class="vista-previa text-xs font-bold text-sky-700">—</span>
                                            <input type="text" name="lecturas[{{ $id }}][observacion]" maxlength="255" list="observaciones-comunes"
                                                   value="{{ old("lecturas.{$id}.observacion") }}" placeholder="📝 Observación (opcional)"
                                                   class="observacion mt-2 w-full sm:w-56 px-3 py-1.5 rounded-xl border border-sky-200 text-xs font-semibold text-blue-950 focus:outline-none focus:ring-2 focus:ring-blue-500/50">
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-6 py-10 text-center font-bold text-sky-700">No hay socios registrados.</td></tr>
                            @endforelse
                            {{-- Sugerencias para la observación (el plomero puede escribir otra cosa) --}}
                            <datalist id="observaciones-comunes">
                                @foreach(['Medidor dañado', 'Medidor empañado o no se ve bien', 'Posible fuga', 'Medidor cambiado', 'Conexión sin medidor', 'Lectura tomada con dificultad (acceso cerrado)'] as $sugerencia)
                                    <option value="{{ $sugerencia }}"></option>
                                @endforeach
                            </datalist>
                            {{-- Se oculta con style (no con .hidden): en el celular .tabla-responsiva tr fuerza display:block --}}
                            <tr id="sin-coincidencias" style="display: none">
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

                const observacion = fila.querySelector('input.observacion');
                const conFoto = fila.dataset.foto === '1';
                if (actual.value === '' && ((observacion && observacion.value.trim() !== '') || conFoto)) {
                    actual.classList.add('border-red-500');
                    previa.textContent = '❌ Falta la lectura actual';
                    previa.className = 'vista-previa text-xs font-bold text-red-700';
                    return { escrita: true, error: true };
                }
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

            // ---------- Guardado en el celular y envío (funciona sin señal) ----------
            // Lo escrito se guarda en el celular a cada tecla. Al guardar sin señal queda
            // "pendiente" y se envía solo cuando vuelve la conexión (o al volver a ingresar).
            const form = document.getElementById('planilla');
            const prefijo = 'h2o-planilla:{{ auth()->id() }}:';
            const claveActual = prefijo + '{{ $gestion }}-{{ $mes }}';
            const nombreMes = @json(\App\Support\Meses::NOMBRES);

            const leer = (clave) => { try { return JSON.parse(localStorage.getItem(clave)) || null; } catch { return null; } };
            const escribir = (clave, valor) => { try { localStorage.setItem(clave, JSON.stringify(valor)); } catch {} };

            function datosDelFormulario() {
                const lecturas = {};
                form.querySelectorAll('input[name^="lecturas["]').forEach(campo => {
                    const [, id, tipo] = campo.name.match(/lecturas\[(\d+)\]\[(\w+)\]/);
                    if (campo.value === '') return;
                    (lecturas[id] ??= {})[tipo] = campo.value;
                });
                return lecturas;
            }

            function guardarBorrador(pendiente = null) {
                const anterior = leer(claveActual);
                const lecturas = datosDelFormulario();
                if (!Object.keys(lecturas).length && !anterior?.pendiente) { localStorage.removeItem(claveActual); return; }
                escribir(claveActual, {
                    mes: {{ $mes }}, gestion: {{ $gestion }}, lecturas,
                    pendiente: pendiente ?? anterior?.pendiente ?? false,
                    actualizado: new Date().toISOString(),
                });
            }

            // Al abrir: recupera lo escrito antes (si no vino ya del servidor)
            const borrador = leer(claveActual);
            if (borrador) {
                Object.entries(borrador.lecturas).forEach(([id, valores]) => Object.entries(valores).forEach(([tipo, valor]) => {
                    const campo = form.querySelector(`input[name="lecturas[${id}][${tipo}]"]`);
                    if (campo && campo.value === '') campo.value = valor;
                }));
                resumen();
            }
            form.addEventListener('input', () => guardarBorrador());

            const estado = document.getElementById('estado-envio');
            function avisar(texto, tipo, { reintentar = false, login = false } = {}) {
                const colores = { espera: 'bg-amber-50 border-amber-500 text-amber-900', error: 'bg-red-50 border-red-500 text-red-900' };
                estado.className = 'p-4 rounded-r-xl border-l-4 shadow-md text-sm font-bold ' + colores[tipo];
                document.getElementById('estado-texto').textContent = texto;
                document.getElementById('enviar-ahora').classList.toggle('hidden', !reintentar);
                document.getElementById('ir-login').classList.toggle('hidden', !login);
            }

            function tokenCsrf() {
                const cookie = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='));
                return cookie ? { 'X-XSRF-TOKEN': decodeURIComponent(cookie.split('=')[1]) }
                    : { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content };
            }

            function mostrarErrores(errores) {
                form.querySelectorAll('.error-fila').forEach(e => { e.textContent = ''; e.classList.add('hidden'); });
                Object.entries(errores).forEach(([clave, mensaje]) => {
                    const id = clave.split('.')[1];
                    const div = form.querySelector(`tr[data-id="${id}"] .error-fila`);
                    if (div) { div.textContent = mensaje; div.classList.remove('hidden'); }
                });
            }

            // Envía un borrador al servidor. Devuelve 'ok', 'sin-senal', 'sesion' o 'errores'.
            async function enviar(clave) {
                const datos = leer(clave);
                if (!datos || !Object.keys(datos.lecturas).length) { localStorage.removeItem(clave); return 'ok'; }
                let respuesta;
                try {
                    respuesta = await fetch(form.action, {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', ...tokenCsrf() },
                        body: JSON.stringify({ mes: datos.mes, gestion: datos.gestion, lecturas: datos.lecturas }),
                    });
                } catch { return 'sin-senal'; }

                // Sesión vencida: el servidor pide volver a ingresar (o redirige al login)
                if (respuesta.status === 419 || respuesta.status === 401 || respuesta.redirected) return 'sesion';
                if (respuesta.status === 422) {
                    const cuerpo = await respuesta.json().catch(() => ({}));
                    escribir(clave, { ...datos, pendiente: false });
                    if (clave === claveActual && cuerpo.errores) mostrarErrores(cuerpo.errores);
                    avisar(cuerpo.errores
                        ? 'Hay lecturas con errores (en rojo). Corrígelas y vuelve a guardar.'
                        : (cuerpo.mensaje || 'No se pudo guardar.'), 'error');
                    return 'errores';
                }
                if (!respuesta.ok) return 'sin-senal';
                const cuerpo = await respuesta.json().catch(() => ({}));
                await asociarFotos(clave, cuerpo.ids || {});
                localStorage.removeItem(clave);
                return 'ok';
            }

            const pendientes = () => Object.keys(localStorage).filter(k => k.startsWith(prefijo) && leer(k)?.pendiente);

            async function mostrarPendientes(motivo = 'sin-senal') {
                const claves = pendientes();
                const fotos = (await fotosPorSubir()).length;
                if (!claves.length && !fotos) return;
                const cantidad = claves.reduce((n, k) => n + Object.values(leer(k).lecturas).filter(l => l.actual).length, 0);
                const meses = (claves.map(k => { const d = leer(k); return nombreMes[d.mes] + ' ' + d.gestion; }).join(', ') || 'ya registradas')
                    + (fotos ? ` · ${fotos} foto(s) por subir` : '');
                if (motivo === 'sesion') {
                    avisar(`🔒 Tu sesión venció. Hay ${cantidad} lectura(s) guardadas en este celular (${meses}): ingresa de nuevo y se enviarán solas.`, 'error', { login: true });
                } else {
                    avisar(`📶 Sin señal: ${cantidad} lectura(s) guardadas en este celular (${meses}). Se enviarán solas cuando vuelva la señal.`, 'espera', { reintentar: true });
                }
            }

            let enviando = false;
            async function enviarPendientes() {
                const claves = pendientes();
                if (enviando || (!claves.length && !(await fotosPorSubir()).length)) return;
                enviando = true;
                avisar('⏳ Enviando las lecturas guardadas en el celular…', 'espera');
                let resultado = 'ok';
                for (const clave of claves) {
                    resultado = await enviar(clave);
                    if (resultado !== 'ok') break;
                }
                // Después de las lecturas, las fotos (pueden quedar para más tarde si se corta la señal)
                if (resultado === 'ok') resultado = await subirFotosPendientes();
                enviando = false;
                if (resultado === 'ok') { location.reload(); return; }
                if (resultado !== 'errores') mostrarPendientes(resultado);
            }

            document.getElementById('enviar-ahora').addEventListener('click', enviarPendientes);
            window.addEventListener('online', enviarPendientes);
            window.addEventListener('offline', () => mostrarPendientes());

            form.addEventListener('submit', e => {
                e.preventDefault();
                const r = resumen();
                if (r.cantidad === 0) { alert('No escribiste ninguna lectura.'); return; }
                if (r.errores.length) { r.errores[0].querySelector('input.actual, input.anterior')?.focus(); alert('Hay lecturas con errores (marcadas en rojo). Corrígelas antes de guardar.'); return; }
                const aviso = r.anormales.length ? '\n\n⚠️ Consumo muy alto en: ' + r.anormales.join(', ') + '.' : '';
                if (!confirm('¿Guardar ' + r.cantidad + ' lectura(s)?' + aviso)) return;

                guardarBorrador(true);
                if (navigator.onLine) enviarPendientes(); else mostrarPendientes();
            });


            // ---------- Foto del medidor ----------
            // Se comprime en el celular (máx. 1280 px, JPEG) y se guarda en IndexedDB
            // (aguanta muchas fotos sin señal). Cuando la lectura se registra, se sube.
            const almacen = (() => {
                let base;
                const abrir = () => base ??= new Promise((ok, mal) => {
                    const pedido = indexedDB.open('h2o-control', 1);
                    pedido.onupgradeneeded = () => pedido.result.createObjectStore('fotos');
                    pedido.onsuccess = () => ok(pedido.result);
                    pedido.onerror = () => mal(pedido.error);
                });
                const operar = async (modo, fn) => {
                    const db = await abrir();
                    return new Promise((ok, mal) => {
                        const tx = db.transaction('fotos', modo);
                        const pedido = fn(tx.objectStore('fotos'));
                        tx.oncomplete = () => ok(pedido?.result);
                        tx.onerror = () => mal(tx.error);
                    });
                };
                return {
                    poner: (clave, valor) => operar('readwrite', s => s.put(valor, clave)),
                    quitar: (clave) => operar('readwrite', s => s.delete(clave)),
                    todas: async () => {
                        const db = await abrir();
                        return new Promise((ok) => {
                            const lista = [];
                            const cursor = db.transaction('fotos').objectStore('fotos').openCursor();
                            cursor.onsuccess = () => {
                                const c = cursor.result;
                                if (c) { if (String(c.key).startsWith(prefijo)) lista.push([c.key, c.value]); c.continue(); } else ok(lista);
                            };
                            cursor.onerror = () => ok(lista);
                        });
                    },
                };
            })();

            async function comprimir(archivo) {
                const imagen = await createImageBitmap(archivo, { imageOrientation: 'from-image' });
                const escala = Math.min(1, 1280 / Math.max(imagen.width, imagen.height));
                const lienzo = document.createElement('canvas');
                lienzo.width = Math.round(imagen.width * escala);
                lienzo.height = Math.round(imagen.height * escala);
                lienzo.getContext('2d').drawImage(imagen, 0, 0, lienzo.width, lienzo.height);
                return new Promise(ok => lienzo.toBlob(ok, 'image/jpeg', 0.7));
            }

            function mostrarFoto(fila, blob) {
                const mini = fila.querySelector('.miniatura');
                if (mini.src) URL.revokeObjectURL(mini.src);
                fila.dataset.foto = blob ? '1' : '';
                mini.classList.toggle('hidden', !blob);
                fila.querySelector('.quitar-foto').classList.toggle('hidden', !blob);
                fila.querySelector('.texto-foto').textContent = blob ? 'Cambiar' : 'Foto';
                if (blob) mini.src = URL.createObjectURL(blob);
                else mini.removeAttribute('src');
                resumen();
            }

            form.querySelectorAll('tr.fila').forEach(fila => {
                const entrada = fila.querySelector('.entrada-foto');
                if (!entrada) return;
                const clave = claveActual + '|' + fila.dataset.id;
                entrada.addEventListener('change', async () => {
                    const archivo = entrada.files[0];
                    entrada.value = '';
                    if (!archivo) return;
                    try {
                        const blob = await comprimir(archivo);
                        await almacen.poner(clave, { blob, socio: fila.dataset.id, lectura: null });
                        mostrarFoto(fila, blob);
                    } catch (error) {
                        alert('No se pudo guardar la foto en el celular. Intenta de nuevo.');
                    }
                });
                fila.querySelector('.quitar-foto').addEventListener('click', async () => {
                    await almacen.quitar(clave);
                    mostrarFoto(fila, null);
                });
            });

            // Al abrir: muestra las fotos tomadas antes (sin señal o sin guardar)
            almacen.todas().then(fotos => fotos.forEach(([clave, foto]) => {
                if (!clave.startsWith(claveActual + '|') || foto.lectura) return;
                const fila = form.querySelector(`tr.fila[data-id="${foto.socio}"]`);
                if (fila && fila.querySelector('.entrada-foto')) mostrarFoto(fila, foto.blob);
            })).catch(() => {});

            // Las lecturas ya se registraron: cada foto sabe a qué lectura va
            async function asociarFotos(clave, ids) {
                for (const [claveFoto, foto] of await almacen.todas()) {
                    if (!claveFoto.startsWith(clave + '|') || foto.lectura) continue;
                    if (ids[foto.socio]) await almacen.poner(claveFoto, { ...foto, lectura: ids[foto.socio] });
                    else await almacen.quitar(claveFoto); // su lectura no se envió: no hay a qué asociarla
                }
            }

            const fotosPorSubir = async () => (await almacen.todas().catch(() => [])).filter(([, foto]) => foto.lectura);

            // Sube las fotos ya asociadas. Devuelve 'ok', 'sin-senal' o 'sesion'.
            async function subirFotosPendientes() {
                for (const [clave, foto] of await fotosPorSubir()) {
                    const datos = new FormData();
                    datos.append('foto', foto.blob, 'medidor.jpg');
                    let respuesta;
                    try {
                        respuesta = await fetch(`{{ url('/lecturas') }}/${foto.lectura}/foto`, {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'Accept': 'application/json', ...tokenCsrf() }, body: datos,
                        });
                    } catch { return 'sin-senal'; }
                    if (respuesta.status === 419 || respuesta.status === 401 || respuesta.redirected) return 'sesion';
                    if (!respuesta.ok && ![409, 404, 422].includes(respuesta.status)) return 'sin-senal';
                    await almacen.quitar(clave); // subida, o ya tenía foto / la lectura no existe
                }
                return 'ok';
            }

            // Al abrir con señal, envía lo que haya quedado pendiente (lecturas y fotos)
            if (navigator.onLine) enviarPendientes(); else mostrarPendientes();

            // ---------- Instalar como app ----------
            const enApp = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
            let pedidoInstalar = null;
            window.addEventListener('beforeinstallprompt', e => {
                e.preventDefault();
                pedidoInstalar = e;
                if (!enApp) {
                    document.getElementById('instalar-app').classList.remove('hidden');
                    document.getElementById('instalar').classList.remove('hidden');
                }
            });
            document.getElementById('instalar').addEventListener('click', async () => {
                if (!pedidoInstalar) return;
                pedidoInstalar.prompt();
                await pedidoInstalar.userChoice;
                pedidoInstalar = null;
                document.getElementById('instalar-app').classList.add('hidden');
            });
            if (!enApp && /iphone|ipad|ipod/i.test(navigator.userAgent)) {
                document.getElementById('instalar-app').classList.remove('hidden');
                document.getElementById('instalar-iphone').classList.remove('hidden');
            }

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
                document.getElementById('sin-coincidencias').style.display = (visibles > 0 || filas.length === 0) ? 'none' : '';
            }
            buscar.addEventListener('input', filtrar);
            soloFaltan.addEventListener('change', filtrar);
            if (zona) zona.addEventListener('change', filtrar);

            filtrar();
            resumen();
        })();
    </script>
</x-app-layout>
