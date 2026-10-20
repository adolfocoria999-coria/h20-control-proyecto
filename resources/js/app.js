import './bootstrap';

import Alpine from 'alpinejs';
import { iniciarVerContrasena } from './ver-contrasena';

window.Alpine = Alpine;

Alpine.start();

// "Ojito" para ver la contraseña en todos los formularios
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciarVerContrasena);
} else {
    iniciarVerContrasena();
}
