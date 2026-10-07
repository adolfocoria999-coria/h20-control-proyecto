{{-- Botón único para exportar a Excel. Uso: <x-boton-excel :href="route('modulo.exportar', request()->query())" /> --}}
@props(['href'])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex items-center justify-center gap-2 whitespace-nowrap bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-3 rounded-xl font-extrabold text-sm shadow-md transition-colors']) }}>
    <span aria-hidden="true">📥</span> Exportar a Excel
</a>
