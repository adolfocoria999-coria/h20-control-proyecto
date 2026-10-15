{{-- Botón para descargar un PDF. Uso: <x-boton-pdf :href="route('arqueo.pdf', [...])">Acta en PDF</x-boton-pdf>
     Con chico: botón compacto para las filas de una tabla (recibos). --}}
@props(['href', 'chico' => false])

<a href="{{ $href }}" {{ $attributes->merge(['class' => $chico
    ? 'inline-flex items-center justify-center gap-1 whitespace-nowrap bg-rose-600 hover:bg-rose-700 text-white px-3 py-1.5 rounded-xl text-xs font-bold shadow-sm transition-all hover:scale-105 h-8'
    : 'inline-flex items-center justify-center gap-2 whitespace-nowrap bg-rose-600 hover:bg-rose-700 text-white px-5 py-3 rounded-xl font-extrabold text-sm shadow-md transition-colors']) }}>
    <span aria-hidden="true">📄</span> {{ $slot->isEmpty() ? 'Descargar PDF' : $slot }}
</a>
