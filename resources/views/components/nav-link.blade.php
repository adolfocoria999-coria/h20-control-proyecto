@props(['active'])

{{-- Opción del menú superior (barra azul) --}}
@php
$classes = ($active ?? false)
            ? 'inline-flex items-center whitespace-nowrap px-1 pt-1 border-b-4 border-sky-300 text-sm font-bold leading-5 text-white focus:outline-none focus:border-sky-200 transition duration-150 ease-in-out'
            : 'inline-flex items-center whitespace-nowrap px-1 pt-1 border-b-4 border-transparent text-sm font-medium leading-5 text-blue-100 hover:text-white hover:border-blue-400 focus:outline-none focus:text-white focus:border-blue-400 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
