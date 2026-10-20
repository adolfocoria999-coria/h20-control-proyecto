/*
 * Botón "ojito" en todos los campos de contraseña: muestra u oculta lo que se
 * va escribiendo. Se agrega solo a cada <input type="password"> de la página.
 */
const OJO = '<svg aria-hidden="true" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.04 12.32a1 1 0 0 1 0-.64C3.42 7.51 7.36 4.5 12 4.5c4.64 0 8.57 3.01 9.96 7.18a1 1 0 0 1 0 .64C20.58 16.49 16.64 19.5 12 19.5c-4.64 0-8.57-3.01-9.96-7.18Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>';
const OJO_TACHADO = '<svg aria-hidden="true" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.22A10.48 10.48 0 0 0 2.04 12c1.38 4.17 5.32 7.5 9.96 7.5 1.99 0 3.86-.55 5.45-1.5M6.23 6.23A10.45 10.45 0 0 1 12 4.5c4.64 0 8.57 3.01 9.96 7.18a10.5 10.5 0 0 1-4.29 5.55M6.23 6.23 3 3m3.23 3.23 3.65 3.65m7.89 7.89L21 21m-3.23-3.23-3.65-3.65m0 0a3 3 0 1 0-4.24-4.24m4.24 4.24L9.88 9.88"/></svg>';

function agregarOjo(campo) {
    if (campo.dataset.conOjo) return;
    campo.dataset.conOjo = '1';

    // El botón se ubica dentro del campo, a la derecha
    let contenedor = campo.parentElement;
    if (getComputedStyle(contenedor).position === 'static' || contenedor.children.length > 1 && !contenedor.classList.contains('relative')) {
        const envoltura = document.createElement('div');
        envoltura.className = 'relative';
        campo.parentNode.insertBefore(envoltura, campo);
        envoltura.appendChild(campo);
        contenedor = envoltura;
    }
    campo.style.paddingRight = '2.75rem';

    const boton = document.createElement('button');
    boton.type = 'button';
    boton.className = 'absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-blue-700 focus:outline-none focus:text-blue-700';
    boton.setAttribute('aria-label', 'Mostrar contraseña');
    boton.setAttribute('aria-pressed', 'false');
    boton.innerHTML = OJO;

    boton.addEventListener('click', () => {
        const visible = campo.type === 'password';
        campo.type = visible ? 'text' : 'password';
        boton.innerHTML = visible ? OJO_TACHADO : OJO;
        boton.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
        boton.setAttribute('aria-pressed', String(visible));
        campo.focus();
    });

    // Al enviar el formulario vuelve a ocultarse (no queda a la vista ni en el historial del navegador)
    campo.form?.addEventListener('submit', () => { campo.type = 'password'; });

    contenedor.appendChild(boton);
}

export function iniciarVerContrasena() {
    document.querySelectorAll('input[type="password"]').forEach(agregarOjo);
}
