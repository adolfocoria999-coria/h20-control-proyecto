{{--
    Modal para confirmar un cobro eligiendo el método de pago (QR o efectivo).
    Va una sola vez por página; cada fila lo abre con <x-boton-pagar>.

    Uso: <x-modal-pago metodo-http="PATCH" />
--}}
@props(['metodoHttp' => 'POST'])

<div x-data="{ abierto: false, accion: '', concepto: '', monto: '' }"
     x-on:abrir-pago.window="abierto = true; accion = $event.detail.accion; concepto = $event.detail.concepto; monto = $event.detail.monto; $nextTick(() => $refs.qr.focus())"
     x-on:keydown.escape.window="abierto = false"
     x-show="abierto"
     x-cloak
     class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="modal-pago-titulo">

    <div class="absolute inset-0 bg-blue-950/60" x-on:click="abierto = false" x-show="abierto" x-transition.opacity></div>

    <div class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl p-6" x-show="abierto" x-transition>
        <h3 id="modal-pago-titulo" class="text-xl font-black text-blue-950">Confirmar pago</h3>
        <p class="mt-1 text-sm font-bold text-sky-800" x-text="concepto"></p>
        <p class="mt-3 text-3xl font-black text-emerald-600" x-text="monto"></p>

        <p class="mt-5 text-sm font-extrabold text-blue-950">¿Cómo pagó el socio?</p>

        <form x-bind:action="accion" method="POST" class="mt-3">
            @csrf
            @if(strtoupper($metodoHttp) !== 'POST')
                @method($metodoHttp)
            @endif

            <div class="grid grid-cols-2 gap-3">
                <button type="submit" name="metodo_pago" value="{{ \App\Enums\MetodoPago::Qr->value }}" x-ref="qr"
                        class="flex flex-col items-center justify-center gap-1 py-4 rounded-xl border-2 border-blue-200 bg-blue-50 hover:bg-blue-100 hover:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 font-extrabold text-blue-900">
                    <span class="text-3xl" aria-hidden="true">📱</span> QR
                </button>
                <button type="submit" name="metodo_pago" value="{{ \App\Enums\MetodoPago::Efectivo->value }}"
                        class="flex flex-col items-center justify-center gap-1 py-4 rounded-xl border-2 border-emerald-200 bg-emerald-50 hover:bg-emerald-100 hover:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 font-extrabold text-emerald-900">
                    <span class="text-3xl" aria-hidden="true">💵</span> Efectivo
                </button>
            </div>

            <button type="button" x-on:click="abierto = false"
                    class="mt-3 w-full py-3 rounded-xl bg-gray-200 hover:bg-gray-300 font-bold text-sm text-gray-800">
                Cancelar
            </button>
        </form>
    </div>
</div>
