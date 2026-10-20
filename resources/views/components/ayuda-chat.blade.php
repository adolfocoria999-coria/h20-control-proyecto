{{-- Asistente "💬 Ayuda": botón flotante y ventana de chat. Responde con el manual según el rol. --}}
@php
    $rol = auth()->user()?->rolUsuario()?->alias() ?? 'socio';
    $sugerencias = [
        'socio' => ['¿Cómo pago con QR?', '¿Dónde veo mi recibo?', '¿Cuánto debo?', '¿Cómo instalo la app?'],
        'plomero' => ['¿Cómo cargo lecturas sin señal?', '¿Cómo anoto una fuga?', 'La lectura es menor que la anterior', '¿Cómo instalo la app?'],
        'admin' => ['¿Cómo cobro una lectura?', '¿Cómo aviso a los socios?', '¿Cómo registro el balance del mes?', '¿Cómo hago un respaldo?'],
        'superadmin' => ['¿Cómo registro un socio?', '¿Cómo cambio la tarifa?', 'Un socio olvidó su contraseña', '¿Cómo hago un respaldo?'],
    ][$rol] ?? [];
    // Manual de este rol en la página: si no hay señal, se busca aquí mismo
    $manual = array_map(fn ($s) => ['titulo' => $s['titulo'], 'claves' => $s['claves'], 'texto' => $s['texto']],
        \App\Services\Ayuda::manual(auth()->user()?->rolUsuario()));
@endphp

{{-- En la planilla el botón sube para no tapar la barra de "Guardar" --}}
<style>body:has(#planilla) #ayuda-abrir { bottom: 9.5rem; }</style>

<div id="ayuda" class="print:hidden">
    <button type="button" id="ayuda-abrir" aria-label="Abrir ayuda" aria-expanded="false"
            class="fixed right-4 bottom-4 sm:bottom-6 z-40 inline-flex items-center gap-2 bg-blue-700 hover:bg-blue-800 text-white pl-4 pr-5 py-3 rounded-full shadow-xl font-extrabold text-sm">
        <span class="text-lg" aria-hidden="true">💬</span> Ayuda
    </button>

    <section id="ayuda-ventana" role="dialog" aria-label="Asistente de ayuda"
             class="hidden fixed z-50 inset-x-2 bottom-2 top-16 sm:inset-auto sm:right-6 sm:bottom-6 sm:w-96 sm:h-[34rem] bg-white rounded-2xl shadow-2xl border border-sky-100 flex flex-col overflow-hidden">
        <header class="flex items-center justify-between gap-3 px-4 py-3 bg-blue-700 text-white">
            <div>
                <p class="font-extrabold">💬 Ayuda de H2O Control</p>
                <p class="text-[0.7rem] font-semibold text-blue-100">Te explico cómo usar el sistema</p>
            </div>
            <button type="button" id="ayuda-cerrar" class="text-xl leading-none px-1 text-white/80 hover:text-white" aria-label="Cerrar ayuda">✕</button>
        </header>

        <div id="ayuda-mensajes" class="flex-1 overflow-y-auto p-4 space-y-3 bg-sky-50 text-sm" aria-live="polite">
            <div class="mensaje-asistente max-w-[90%] bg-white border border-sky-100 rounded-2xl rounded-tl-sm px-3 py-2 text-blue-950 shadow-sm">
                ¡Hola! 👋 Pregúntame cómo hacer algo en el sistema. También puedes tocar una de estas preguntas:
            </div>
            <div class="flex flex-wrap gap-2" id="ayuda-sugerencias">
                @foreach($sugerencias as $sugerencia)
                    <button type="button" class="sugerencia px-3 py-1.5 rounded-full bg-white border border-blue-200 text-blue-800 text-xs font-bold hover:bg-blue-50">{{ $sugerencia }}</button>
                @endforeach
            </div>
        </div>

        <form id="ayuda-form" class="flex items-end gap-2 p-3 border-t border-sky-100 bg-white">
            <label for="ayuda-pregunta" class="sr-only">Tu pregunta</label>
            <textarea id="ayuda-pregunta" rows="1" maxlength="300" placeholder="Escribe tu pregunta…"
                      class="flex-1 resize-none rounded-xl border border-sky-200 px-3 py-2 text-sm text-blue-950 focus:outline-none focus:ring-2 focus:ring-blue-500/50"></textarea>
            <button type="submit" class="shrink-0 bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded-xl font-extrabold text-sm disabled:opacity-50">Enviar</button>
        </form>
        <p class="px-3 pb-2 text-[0.65rem] text-gray-500 bg-white">Responde con el manual del sistema; no ve tus datos personales. Para dudas sobre tu cuenta: Hacienda {{ config('otb.telefono_finanzas') }}.</p>
    </section>
</div>

<script>
    (function () {
        const abrir = document.getElementById('ayuda-abrir');
        const ventana = document.getElementById('ayuda-ventana');
        const mensajes = document.getElementById('ayuda-mensajes');
        const form = document.getElementById('ayuda-form');
        const campo = document.getElementById('ayuda-pregunta');
        const manual = @json($manual);
        const conversacion = [];

        const alternar = (abierta) => {
            ventana.classList.toggle('hidden', !abierta);
            abrir.classList.toggle('hidden', abierta);
            abrir.setAttribute('aria-expanded', String(abierta));
            if (abierta) campo.focus();
        };
        abrir.addEventListener('click', () => alternar(true));
        document.getElementById('ayuda-cerrar').addEventListener('click', () => alternar(false));
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && !ventana.classList.contains('hidden')) alternar(false); });

        // Texto seguro con **negritas**, `código`, listas y saltos de línea
        function formato(texto) {
            const seguro = texto.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            return seguro
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/`([^`]+)`/g, '<code class="px-1 bg-sky-100 rounded">$1</code>')
                .replace(/^#{1,4}\s*(.+)$/gm, '<strong>$1</strong>')
                .replace(/^\s*[-*]\s+(.+)$/gm, '• $1')
                .replace(/\n/g, '<br>');
        }

        function agregar(rol, texto, extra = '') {
            const burbuja = document.createElement('div');
            burbuja.className = rol === 'usuario'
                ? 'ml-auto max-w-[85%] bg-blue-700 text-white rounded-2xl rounded-tr-sm px-3 py-2 shadow-sm'
                : 'mensaje-asistente max-w-[90%] bg-white border border-sky-100 rounded-2xl rounded-tl-sm px-3 py-2 text-blue-950 shadow-sm';
            burbuja.innerHTML = formato(texto) + extra;
            mensajes.appendChild(burbuja);
            mensajes.scrollTop = mensajes.scrollHeight;
            return burbuja;
        }

        // Búsqueda en el manual del propio dispositivo (sin señal)
        function buscarLocal(pregunta) {
            const limpiar = t => t.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').match(/[a-z0-9]{3,}/g) || [];
            const palabras = limpiar(pregunta);
            const raiz = w => w.slice(0, 4);
            const mejor = manual.map(s => {
                const titulo = limpiar(s.titulo), claves = limpiar(s.claves);
                const puntaje = palabras.reduce((n, p) => n + (titulo.some(w => raiz(w) === raiz(p)) ? 3 : 0) + (claves.some(w => raiz(w) === raiz(p)) ? 2 : 0), 0);
                return [puntaje, s];
            }).filter(([p]) => p > 0).sort((a, b) => b[0] - a[0])[0];
            return mejor
                ? `**${mejor[1].titulo}**\n${mejor[1].texto}`
                : 'Sin señal no encontré eso en el manual. Prueba con otras palabras o pregunta de nuevo cuando tengas conexión.';
        }

        async function preguntar(pregunta) {
            pregunta = pregunta.trim();
            if (!pregunta) return;
            document.getElementById('ayuda-sugerencias')?.remove();
            agregar('usuario', pregunta);
            campo.value = '';
            const espera = agregar('asistente', '⏳ Buscando en el manual…');
            form.querySelector('button').disabled = true;

            let respuesta, nota = '';
            try {
                const r = await fetch(@json(route('ayuda.preguntar')), {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ pregunta, conversacion: conversacion.slice(-4) }),
                });
                const datos = await r.json();
                respuesta = datos.respuesta || 'No pude responder ahora. Intenta de nuevo.';
                if (datos.modo === 'ia' && datos.fuentes?.length) nota = `<div class="mt-2 text-[0.65rem] text-sky-700">📖 Según el manual: ${datos.fuentes.slice(0, 2).map(f => formato(f)).join(' · ')}</div>`;
            } catch {
                respuesta = buscarLocal(pregunta);
                nota = '<div class="mt-2 text-[0.65rem] text-amber-700">📶 Sin señal: respuesta del manual guardado en este dispositivo.</div>';
            }

            espera.remove();
            agregar('asistente', respuesta, nota);
            conversacion.push({ rol: 'usuario', texto: pregunta }, { rol: 'asistente', texto: respuesta });
            form.querySelector('button').disabled = false;
            campo.focus();
        }

        form.addEventListener('submit', e => { e.preventDefault(); preguntar(campo.value); });
        campo.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); preguntar(campo.value); } });
        document.querySelectorAll('#ayuda .sugerencia').forEach(b => b.addEventListener('click', () => preguntar(b.textContent)));
    })();
</script>
