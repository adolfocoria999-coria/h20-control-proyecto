@php
    $urgente = in_array($qr->estado(), [\App\Services\QrPago::VENCIDO, \App\Services\QrPago::SIN_QR], true);
@endphp

<div class="print:hidden {{ $urgente ? 'bg-red-600' : 'bg-amber-400' }}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2.5 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm font-extrabold {{ $urgente ? 'text-white' : 'text-amber-950' }}">
            {{ $urgente ? '⛔' : '⚠️' }} {{ $qr->mensaje() }}
        </p>
        @unless(request()->routeIs('finanzas.index'))
            <a href="{{ route('finanzas.index') }}#qr"
               class="text-xs font-black px-3 py-1.5 rounded-lg {{ $urgente ? 'bg-white text-red-700' : 'bg-amber-950 text-amber-100' }} hover:opacity-90">
                Actualizar QR
            </a>
        @endunless
    </div>
</div>
