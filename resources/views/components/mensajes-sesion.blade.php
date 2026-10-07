{{-- Mensajes de éxito o error que deja el controlador tras una acción --}}
@if(session('success'))
    <div {{ $attributes->merge(['class' => 'p-4 bg-emerald-50 border-l-4 border-emerald-600 rounded-r-xl shadow-md']) }} role="status">
        <span class="text-emerald-900 font-bold">{{ session('success') }}</span>
    </div>
@endif
@if(session('error'))
    <div {{ $attributes->merge(['class' => 'p-4 bg-red-50 border-l-4 border-red-600 rounded-r-xl shadow-md']) }} role="alert">
        <span class="text-red-900 font-bold">{{ session('error') }}</span>
    </div>
@endif
@if($errors->has('metodo_pago'))
    <div {{ $attributes->merge(['class' => 'p-4 bg-red-50 border-l-4 border-red-600 rounded-r-xl shadow-md']) }} role="alert">
        <span class="text-red-900 font-bold">{{ $errors->first('metodo_pago') }}</span>
    </div>
@endif
