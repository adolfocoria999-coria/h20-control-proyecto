{{-- Lista de errores de validación del formulario --}}
@if ($errors->any())
    <div class="mb-6 p-4 bg-red-100 border-l-4 border-red-500 rounded-r-xl">
        <p class="font-bold text-red-800 text-sm">⚠️ Corrija los siguientes errores:</p>
        <ul class="mt-2 list-disc list-inside text-xs font-semibold text-red-700">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
