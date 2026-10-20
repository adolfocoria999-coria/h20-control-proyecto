{{-- App instalable (PWA): ícono, color y service worker (public/sw.js).
     Al cerrar sesión se borra la copia de la planilla guardada en el celular. --}}
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#1e40af">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('iconos/icono-192.png') }}">
<link rel="apple-touch-icon" href="{{ asset('iconos/apple-touch-icon.png') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="H2O Control">
<script>
    // El navegador avisa que la app se puede instalar (puede llegar antes de que cargue la página):
    // se guarda para mostrar el botón "Instalar" (ver layouts/_instalar-app)
    window.h2oInstalar = { pedido: null };
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        window.h2oInstalar.pedido = e;
        document.dispatchEvent(new Event('h2o-instalable'));
    });
    window.addEventListener('appinstalled', () => {
        window.h2oInstalar.pedido = null;
        document.dispatchEvent(new Event('h2o-instalada'));
    });

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(() => {}));

        document.addEventListener('submit', (e) => {
            if (!e.target.action || !e.target.action.endsWith('/logout')) return;
            // Lecturas anotadas sin señal que todavía no se enviaron
            const pendientes = Object.keys(localStorage).filter(k => k.startsWith('h2o-planilla:') && (JSON.parse(localStorage.getItem(k) || '{}').pendiente));
            if (pendientes.length && !confirm('Hay lecturas guardadas en este celular que todavía no se enviaron. Si cierras sesión quedarán aquí hasta que vuelvas a ingresar. ¿Cerrar sesión igual?')) {
                e.preventDefault();
                return;
            }
            navigator.serviceWorker.controller?.postMessage('borrar-copias');
        }, true);
    }
</script>
