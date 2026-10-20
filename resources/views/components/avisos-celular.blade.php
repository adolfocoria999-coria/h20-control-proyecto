{{-- Activar notificaciones en este celular (Web Push). Siempre se muestra: si algo falta, lo explica. --}}
@php
    try {
        $clavePublica = \App\Services\NotificacionPush::claves()['publicKey'];
        $errorClaves = null;
    } catch (\Throwable $e) {
        report($e);
        $clavePublica = null;
        $errorClaves = $e->getMessage();
    }
@endphp

@unless($clavePublica)
    {{-- El servidor no pudo preparar las claves de los avisos (p. ej. sin soporte de cifrado) --}}
    <div class="p-4 sm:p-5 bg-white rounded-2xl shadow-md border border-amber-200">
        <p class="font-extrabold text-blue-950">🔔 Avisos en este celular</p>
        <p class="text-xs font-bold text-amber-800 mt-0.5">Los avisos en el celular todavía no están disponibles en este servidor.</p>
        @if(auth()->user()?->esAdmin())
            <p class="text-xs text-gray-600 mt-1">Para el administrador: configura WEBPUSH_PUBLIC_KEY y WEBPUSH_PRIVATE_KEY en el hosting. Detalle: {{ \Illuminate\Support\Str::limit($errorClaves, 160) }}</p>
        @endif
    </div>
@endunless

@if($clavePublica)
    <div id="avisos-celular" class="p-4 sm:p-5 bg-white rounded-2xl shadow-md border border-sky-100">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <p class="font-extrabold text-blue-950">🔔 Avisos en este celular</p>
                <p id="avisos-texto" class="text-xs font-bold text-sky-800 mt-0.5">Recibe un aviso cuando se registre tu lectura de agua.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" id="avisos-activar" class="hidden bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 rounded-xl font-extrabold text-sm shadow">Activar avisos</button>
                <button type="button" id="avisos-probar" class="hidden bg-white border border-sky-300 text-blue-900 px-4 py-2 rounded-xl font-extrabold text-sm">Probar</button>
                <button type="button" id="avisos-desactivar" class="hidden bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-xl font-extrabold text-sm">Desactivar</button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const caja = document.getElementById('avisos-celular');
            const texto = document.getElementById('avisos-texto');
            const [activar, probar, desactivar] = ['activar', 'probar', 'desactivar'].map(id => document.getElementById('avisos-' + id));
            const clave = @json($clavePublica);
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            const encabezados = { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token };
            const esIphone = /iphone|ipad|ipod/i.test(navigator.userAgent);
            const enApp = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;

            const mostrar = (mensaje, botones) => {
                texto.textContent = mensaje;
                activar.classList.toggle('hidden', !botones.includes('activar'));
                probar.classList.toggle('hidden', !botones.includes('probar'));
                desactivar.classList.toggle('hidden', !botones.includes('desactivar'));
                caja.classList.remove('hidden');
            };

            // La clave pública viene en base64 "url": el navegador la necesita en bytes
            const aBytes = (base64) => {
                const relleno = '='.repeat((4 - base64.length % 4) % 4);
                const crudo = atob((base64 + relleno).replace(/-/g, '+').replace(/_/g, '/'));
                return Uint8Array.from([...crudo].map(c => c.charCodeAt(0)));
            };

            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                mostrar(esIphone && !enApp
                    ? 'En iPhone, primero instala la app (Compartir ⬆️ → "Agregar a inicio") y ábrela desde el ícono para activar los avisos.'
                    : 'Este navegador no admite avisos. Abre el sistema en Chrome o instala la app (menú → 📲 Instalar app).', []);
                return;
            }

            // Espera el service worker; si no está registrado o no responde, lo registra de nuevo
            async function servicio() {
                const conLimite = (promesa) => Promise.race([promesa, new Promise(r => setTimeout(() => r(null), 6000))]);
                let registro = await conLimite(navigator.serviceWorker.ready);
                if (!registro) {
                    await navigator.serviceWorker.register(@json(asset('sw.js'))).catch(() => null);
                    registro = await conLimite(navigator.serviceWorker.ready);
                }
                if (!registro) throw new Error('sin-service-worker');
                return registro;
            }

            async function actualizar() {
                if (Notification.permission === 'denied') {
                    return mostrar('Bloqueaste las notificaciones de este sitio. Para recibir avisos, permítelas en la configuración del navegador.', []);
                }
                const registro = await servicio();
                const suscripcion = await registro.pushManager.getSubscription();
                suscripcion
                    ? mostrar('✅ Activados: te avisaremos en este celular cuando se registre tu lectura.', ['probar', 'desactivar'])
                    : mostrar('Recibe un aviso en este celular cuando se registre tu lectura de agua.', ['activar']);
            }

            activar.addEventListener('click', async () => {
                activar.disabled = true;
                try {
                    if (await Notification.requestPermission() !== 'granted') return actualizar();
                    const registro = await servicio();
                    const suscripcion = await registro.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: aBytes(clave) });
                    const respuesta = await fetch(@json(route('notificaciones.suscribir')), {
                        method: 'POST', credentials: 'same-origin', headers: encabezados, body: JSON.stringify(suscripcion.toJSON()),
                    });
                    if (!respuesta.ok) throw new Error('servidor');
                } catch (error) {
                    alert('No se pudieron activar los avisos. Revisa tu conexión e intenta de nuevo.');
                } finally {
                    activar.disabled = false;
                    actualizar();
                }
            });

            desactivar.addEventListener('click', async () => {
                const suscripcion = await (await servicio()).pushManager.getSubscription();
                if (suscripcion) {
                    await fetch(@json(route('notificaciones.desuscribir')), {
                        method: 'DELETE', credentials: 'same-origin', headers: encabezados, body: JSON.stringify({ endpoint: suscripcion.endpoint }),
                    }).catch(() => {});
                    await suscripcion.unsubscribe();
                }
                actualizar();
            });

            probar.addEventListener('click', async () => {
                probar.disabled = true;
                const respuesta = await fetch(@json(route('notificaciones.probar')), { method: 'POST', credentials: 'same-origin', headers: encabezados }).catch(() => null);
                probar.disabled = false;
                if (!respuesta || !respuesta.ok) alert('No se pudo enviar la prueba. Desactiva y vuelve a activar los avisos.');
            });

            mostrar('Preparando los avisos…', []);
            actualizar().catch(() => mostrar('Este navegador no permite activar avisos todavía. Abre el sistema desde Chrome (o desde la app instalada) y vuelve a intentarlo.', []));
        })();
    </script>
@endif
