{{-- Completa sola la "lectura anterior" con la del mes previo del socio.
     Requiere los campos #user_id, #mes, #gestion, #lectura_anterior, #lectura_actual y el aviso #ayuda_anterior.
     $ignorar: id de la lectura que se edita (para no tomarse a sí misma). --}}
<script>
    (function () {
        const socio = document.getElementById('user_id');
        const mes = document.getElementById('mes');
        const gestion = document.getElementById('gestion');
        const anterior = document.getElementById('lectura_anterior');
        const actual = document.getElementById('lectura_actual');
        const ayuda = document.getElementById('ayuda_anterior');
        const ignorar = @json($ignorar ?? null);
        const bloqueado = ['bg-gray-100', 'cursor-not-allowed', 'text-gray-500'];

        function aviso(texto, tipo) {
            ayuda.textContent = texto;
            ayuda.className = 'mt-1.5 text-xs font-bold ' + (tipo === 'ok' ? 'text-emerald-700' : 'text-amber-700');
        }

        function m3(valor) {
            return Number(valor).toLocaleString('es-BO', { maximumFractionDigits: 2 }) + ' m³';
        }

        window.obtenerLecturaPrevia = function () {
            if (!socio.value || !mes.value || !gestion.value) {
                anterior.readOnly = false;
                anterior.classList.remove(...bloqueado);
                ayuda.textContent = '';
                return;
            }

            const params = new URLSearchParams({ mes: mes.value, gestion: gestion.value });
            if (ignorar) params.set('ignore_id', ignorar);

            fetch(`{{ url('/lecturas/ultima') }}/${socio.value}?${params}`, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    if (data.tiene_lectura) {
                        // Hay lectura del mes previo: es la lectura anterior y no se cambia
                        anterior.value = Number(data.lectura_actual);
                        anterior.readOnly = true;
                        anterior.classList.add(...bloqueado);
                        aviso(`Se completó con la lectura de ${data.periodo}: ${m3(data.lectura_actual)}.`, 'ok');
                        actual.min = Number(data.lectura_actual);
                    } else {
                        // Primera lectura del socio: se escribe la lectura inicial del medidor
                        anterior.readOnly = false;
                        anterior.classList.remove(...bloqueado);
                        if (anterior.value === '' || anterior.dataset.auto === '1') anterior.value = '';
                        aviso('Primera lectura de este socio: escribe la lectura inicial de su medidor.', 'nuevo');
                        actual.removeAttribute('min');
                    }
                    anterior.dataset.auto = data.tiene_lectura ? '1' : '0';

                    actual.max = data.siguiente ? Number(data.siguiente.lectura_actual) : '';
                    if (data.siguiente) {
                        ayuda.textContent += ` Ya existe la lectura de ${data.siguiente.periodo} (${m3(data.siguiente.lectura_actual)}): la actual no puede superarla.`;
                    }
                })
                .catch(error => console.error('Error al obtener la lectura previa:', error));
        };

        [socio, mes, gestion].forEach(campo => campo.addEventListener('change', window.obtenerLecturaPrevia));
        gestion.addEventListener('input', window.obtenerLecturaPrevia);

        // Al volver con errores o al editar, ya hay un socio elegido
        if (socio.value) window.obtenerLecturaPrevia();
    })();
</script>
