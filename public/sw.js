/*
 * Service worker de H2O Control.
 *
 * - Permite instalar la app en el celular (pantalla de inicio).
 * - Guarda una copia de la planilla de carga de lecturas para que el técnico
 *   pueda abrirla sin señal. Las lecturas escritas sin señal las guarda la
 *   propia planilla en el celular (localStorage) y las envía al volver la señal.
 *
 * Solo se guarda la planilla (y estilos/íconos): el resto de las pantallas
 * siempre se piden al servidor. Al cerrar sesión se borra esta copia.
 */
const VERSION = 'h2o-v2';
const ESTATICOS = `${VERSION}-estaticos`;
const PAGINAS = `${VERSION}-paginas`;

self.addEventListener('install', (evento) => {
    evento.waitUntil(
        caches.open(ESTATICOS)
            .then((cache) => cache.addAll(['/manifest.webmanifest', '/iconos/icono-192.png']))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (evento) => {
    evento.waitUntil(
        caches.keys()
            .then((claves) => Promise.all(claves.filter((c) => !c.startsWith(VERSION)).map((c) => caches.delete(c))))
            .then(() => self.clients.claim())
    );
});

// Al cerrar sesión la página pide borrar la copia de la planilla
self.addEventListener('message', (evento) => {
    if (evento.data === 'borrar-copias') {
        evento.waitUntil(caches.delete(PAGINAS));
    }
});

// Notificación que manda el sistema (p. ej. "💧 Tu lectura de octubre"), aunque la app esté cerrada
self.addEventListener('push', (evento) => {
    let datos = {};
    try {
        datos = evento.data ? evento.data.json() : {};
    } catch {
        datos = { body: evento.data ? evento.data.text() : '' };
    }

    evento.waitUntil(self.registration.showNotification(datos.title || 'H2O Control', {
        body: datos.body || '',
        icon: datos.icon || '/iconos/icono-192.png',
        badge: '/iconos/icono-192.png',
        data: { url: datos.url || '/dashboard' },
        lang: 'es',
    }));
});

// Al tocarla se abre (o enfoca) el sistema en la pantalla indicada
self.addEventListener('notificationclick', (evento) => {
    evento.notification.close();
    const destino = new URL(evento.notification.data?.url || '/dashboard', self.location.origin).href;

    evento.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((ventanas) => {
        const abierta = ventanas.find((v) => v.url === destino);
        return abierta ? abierta.focus() : self.clients.openWindow(destino);
    }));
});

const esPlanilla = (url) => url.pathname === '/lecturas/planilla';
const esEstatico = (url) => url.pathname.startsWith('/build/') || url.pathname.startsWith('/iconos/');

self.addEventListener('fetch', (evento) => {
    const pedido = evento.request;
    const url = new URL(pedido.url);

    if (pedido.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // Planilla: primero la red (datos al día); sin señal, la última copia guardada
    if (esPlanilla(url) && pedido.mode === 'navigate') {
        evento.respondWith(
            fetch(pedido)
                .then((respuesta) => {
                    // Solo se guarda la planilla real (no una redirección al login)
                    if (respuesta.ok && !respuesta.redirected) {
                        const copia = respuesta.clone();
                        caches.open(PAGINAS).then((cache) => cache.put(pedido, copia));
                    }
                    return respuesta;
                })
                .catch(async () => {
                    const cache = await caches.open(PAGINAS);
                    return (await cache.match(pedido)) // el mismo mes
                        || (await cache.match(pedido, { ignoreSearch: true })) // otro mes ya abierto
                        || new Response(PAGINA_SIN_SENAL, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
                })
        );
        return;
    }

    // Estilos, scripts e íconos (llevan versión en el nombre): de la copia si existe
    if (esEstatico(url)) {
        evento.respondWith(
            caches.match(pedido).then((guardado) => guardado || fetch(pedido).then((respuesta) => {
                if (respuesta.ok) {
                    const copia = respuesta.clone();
                    caches.open(ESTATICOS).then((cache) => cache.put(pedido, copia));
                }
                return respuesta;
            }))
        );
    }
});

const PAGINA_SIN_SENAL = `<!doctype html><html lang="es"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>Sin señal – H2O Control</title></head>
<body style="font-family:system-ui,sans-serif;background:#e0f2fe;color:#172554;padding:2rem;text-align:center">
<h1 style="font-size:1.4rem">📶 Sin señal</h1>
<p>Esta planilla todavía no se abrió con internet en este celular.</p>
<p>Ábrela una vez con señal (por ejemplo, antes de salir al recorrido) y después funcionará sin conexión.</p>
<p><a href="/lecturas/planilla" style="color:#1d4ed8;font-weight:bold">Reintentar</a></p>
</body></html>`;
