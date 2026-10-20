{{-- "Instalar la app" en todas las pantallas (socios incluidos): aviso que se puede cerrar,
     opción del menú siempre disponible (por si se borró la app) e instrucciones paso a paso. --}}
<div id="aviso-instalar" class="hidden print:hidden bg-blue-700 text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2.5 flex items-center gap-3">
        <span class="text-xl" aria-hidden="true">📲</span>
        <p class="flex-1 text-xs sm:text-sm font-bold leading-snug">
            Instala H2O Control en tu celular: entras con un toque, funciona sin señal y recibes avisos de tu lectura.
        </p>
        <button type="button" onclick="instalarApp()" class="shrink-0 bg-white text-blue-800 px-3 py-1.5 rounded-lg text-xs sm:text-sm font-extrabold shadow">Instalar</button>
        <button type="button" onclick="cerrarAvisoInstalar()" class="shrink-0 text-white/80 hover:text-white text-lg leading-none px-1" aria-label="Cerrar aviso">✕</button>
    </div>
</div>

{{-- Instrucciones cuando el navegador no ofrece el instalador (iPhone, o ya se cerró el aviso) --}}
<div id="ayuda-instalar" class="hidden fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="ayuda-instalar-titulo"
     onclick="if (event.target === this) this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 space-y-4 text-blue-950">
        <div class="flex items-start justify-between gap-3">
            <h3 id="ayuda-instalar-titulo" class="text-lg font-black">📲 Instalar H2O Control</h3>
            <button type="button" onclick="document.getElementById('ayuda-instalar').classList.add('hidden')" class="text-gray-400 hover:text-gray-700 text-xl leading-none" aria-label="Cerrar">✕</button>
        </div>
        <div id="ayuda-instalar-ya" class="hidden p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-sm font-bold text-emerald-900">
            ✅ Ya estás usando la app instalada.
        </div>
        <div data-ayuda="android" class="space-y-2 text-sm">
            <p class="font-extrabold">En Android (Chrome):</p>
            <ol class="list-decimal list-inside space-y-1 font-semibold">
                <li>Toca el menú <span class="font-black">⋮</span> (arriba a la derecha).</li>
                <li>Elige <span class="font-black">"Instalar aplicación"</span> o <span class="font-black">"Agregar a la pantalla principal"</span>.</li>
                <li>Confirma: aparecerá el ícono de la gota azul 💧.</li>
            </ol>
        </div>
        <div data-ayuda="iphone" class="space-y-2 text-sm">
            <p class="font-extrabold">En iPhone (Safari):</p>
            <ol class="list-decimal list-inside space-y-1 font-semibold">
                <li>Toca <span class="font-black">Compartir</span> ⬆️ (abajo, al centro).</li>
                <li>Elige <span class="font-black">"Agregar a inicio"</span>.</li>
                <li>Toca <span class="font-black">Agregar</span>.</li>
            </ol>
        </div>
        <p class="text-xs font-bold text-sky-800">¿Borraste la app sin querer? Repite estos pasos: tus datos no se pierden, están en el sistema.</p>
    </div>
</div>

<script>
    (function () {
        const CLAVE_CERRADO = 'h2o-aviso-instalar-cerrado';
        const DIAS_SIN_MOLESTAR = 7;
        const aviso = document.getElementById('aviso-instalar');
        const ayuda = document.getElementById('ayuda-instalar');
        const esIphone = /iphone|ipad|ipod/i.test(navigator.userAgent);
        const enApp = () => window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;

        const cerradoHace = () => {
            try { return (Date.now() - Number(localStorage.getItem(CLAVE_CERRADO) || 0)) / 86400000; } catch { return Infinity; }
        };
        const mostrarAviso = () => {
            if (!enApp() && cerradoHace() >= DIAS_SIN_MOLESTAR) aviso.classList.remove('hidden');
        };

        window.cerrarAvisoInstalar = () => {
            aviso.classList.add('hidden');
            try { localStorage.setItem(CLAVE_CERRADO, String(Date.now())); } catch {}
        };

        // Instala con el diálogo del navegador si lo ofrece; si no, muestra los pasos
        window.instalarApp = async () => {
            const pedido = window.h2oInstalar?.pedido;
            if (pedido && !enApp()) {
                pedido.prompt();
                const { outcome } = await pedido.userChoice;
                window.h2oInstalar.pedido = null;
                if (outcome === 'accepted') aviso.classList.add('hidden');
                return;
            }
            document.getElementById('ayuda-instalar-ya').classList.toggle('hidden', !enApp());
            // En iPhone solo los pasos de Safari; en Android o computadora, los de Chrome
            ayuda.querySelector('[data-ayuda="android"]').classList.toggle('hidden', esIphone);
            ayuda.querySelector('[data-ayuda="iphone"]').classList.toggle('hidden', !esIphone);
            ayuda.classList.remove('hidden');
        };

        // Ya instalada (abierta desde el ícono): no se ofrece instalar
        if (enApp()) {
            document.querySelectorAll('.opcion-instalar').forEach(o => o.classList.add('hidden'));
        } else if (window.h2oInstalar?.pedido || esIphone) {
            mostrarAviso();
        }
        document.addEventListener('h2o-instalable', mostrarAviso);
        document.addEventListener('h2o-instalada', () => aviso.classList.add('hidden'));
    })();
</script>
