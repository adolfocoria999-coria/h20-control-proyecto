@props(['claro' => false])

{{-- claro: sobre fondo oscuro (barra de navegación azul) --}}
<div class="flex items-center gap-2">
    <!-- Icono SVG de Gota de Agua con Brote / Hoja -->
    <svg class="w-8 h-8 text-blue-600 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z" fill="{{ $claro ? '#93C5FD' : '#2563EB' }}" />
        <path d="M12 11c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4z" fill="{{ $claro ? '#DBEAFE' : '#60A5FA' }}" opacity="0.6"/>
        <path d="M14 13c1.5 0 2.5 1 2.5 2.5" stroke="#FFFFFF" stroke-width="1.5" stroke-linecap="round" fill="none"/>
    </svg>
    
    <!-- Texto opcional al lado del icono -->
    <span class="font-extrabold text-lg {{ $claro ? 'text-white' : 'text-blue-900' }} tracking-tight">
        H2O <span class="text-blue-600"></span>
    </span>
</div>