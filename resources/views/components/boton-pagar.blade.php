{{--
    Abre <x-modal-pago> para una fila. Uso:
    <x-boton-pagar :accion="route('lecturas.pagar', $lectura)" concepto="Agua – Marzo 2026 – Ana" monto="Bs. 24,00">Confirmar Pago</x-boton-pagar>
--}}
@props(['accion', 'concepto', 'monto'])

<button type="button"
        onclick="window.dispatchEvent(new CustomEvent('abrir-pago', { detail: { accion: this.dataset.accion, concepto: this.dataset.concepto, monto: this.dataset.monto } }))"
        data-accion="{{ $accion }}"
        data-concepto="{{ $concepto }}"
        data-monto="{{ $monto }}"
        {{ $attributes->merge(['class' => 'inline-flex items-center justify-center gap-1 whitespace-nowrap bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3 py-1.5 rounded-lg text-xs shadow-sm']) }}>
    💵 {{ $slot }}
</button>
