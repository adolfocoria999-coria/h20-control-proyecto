{{-- Campos del formulario de socios (alta y edición). $usuario es null al crear. --}}
@php
    $clase = 'shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline';
    $claseError = fn ($campo) => $errors->has($campo) ? ' border-red-500' : '';
@endphp

<x-errores-formulario />

<div class="mb-4">
    <label for="name" class="block text-gray-700 text-sm font-bold mb-2">Nombre Completo</label>
    <input type="text" id="name" name="name" value="{{ old('name', $usuario?->name) }}"
           class="{{ $clase }}{{ $claseError('name') }}" required minlength="3" maxlength="100"
           pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ][A-Za-zÁÉÍÓÚÜÑáéíóúüñ .'\-]*" autocomplete="off"
           placeholder="Ej.: Juan Pérez Mamani" title="Solo letras y espacios, sin números ni símbolos">
    <x-input-error :messages="$errors->get('name')" class="mt-1 text-xs" />
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
    <div>
        <label for="ci" class="block text-gray-700 text-sm font-bold mb-2">Carnet de Identidad (C.I.)</label>
        <input type="text" id="ci" name="ci" value="{{ old('ci', $usuario?->ci) }}"
               class="{{ $clase }}{{ $claseError('ci') }}" maxlength="20" autocomplete="off"
               pattern="\d{5,10}(-[0-9A-Za-z]{2})?( (LP|CB|SC|OR|PT|CH|TJ|BE|PD|lp|cb|sc|or|pt|ch|tj|be|pd))?"
               placeholder="Ej.: 1234567 o 1234567-1A" title="De 5 a 10 números; complemento opcional (-1A) y extensión opcional (LP)">
        <x-input-error :messages="$errors->get('ci')" class="mt-1 text-xs" />
    </div>
    <div>
        <label for="telefono" class="block text-gray-700 text-sm font-bold mb-2">Teléfono (WhatsApp)</label>
        <input type="tel" id="telefono" name="telefono" value="{{ old('telefono', $usuario?->telefono) }}"
               class="{{ $clase }}{{ $claseError('telefono') }}" inputmode="numeric" maxlength="8"
               pattern="[67][0-9]{7}" autocomplete="off"
               oninput="this.value = this.value.replace(/\D/g, '')"
               placeholder="Ej.: 71234567" title="Celular de 8 dígitos que empiece con 6 o 7">
        <x-input-error :messages="$errors->get('telefono')" class="mt-1 text-xs" />
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
    <div>
        <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Correo Electrónico</label>
        <input type="email" id="email" name="email" value="{{ old('email', $usuario?->email) }}"
               class="{{ $clase }}{{ $claseError('email') }}" required maxlength="255" autocomplete="off"
               placeholder="Ej.: juan.perez@gmail.com">
        <x-input-error :messages="$errors->get('email')" class="mt-1 text-xs" />
    </div>
    <div>
        <label for="password" class="block text-gray-700 text-sm font-bold mb-2">
            {{ $usuario ? 'Contraseña (Dejar en blanco para no cambiar)' : 'Contraseña Temporal' }}
        </label>
        <input type="password" id="password" name="password"
               class="{{ $clase }}{{ $claseError('password') }}" minlength="8" maxlength="72" autocomplete="new-password"
               {{ $usuario ? '' : 'required' }} placeholder="{{ $usuario ? 'Nueva contraseña opcional' : 'Mínimo 8 caracteres' }}">
        <x-input-error :messages="$errors->get('password')" class="mt-1 text-xs" />
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
    <div>
        <label for="zona" class="block text-gray-700 text-sm font-bold mb-2">Zona / Calle <span class="font-semibold text-gray-500">(recorrido del plomero)</span></label>
        <input type="text" id="zona" name="zona" value="{{ old('zona', $usuario?->zona) }}" maxlength="60" list="zonas-existentes"
               class="{{ $clase }}{{ $claseError('zona') }}" autocomplete="off" placeholder="Ej.: Zona Norte, Calle 3">
        <datalist id="zonas-existentes">
            @foreach(\App\Models\User::whereNotNull('zona')->distinct()->orderBy('zona')->pluck('zona') as $zona)
                <option value="{{ $zona }}"></option>
            @endforeach
        </datalist>
        <x-input-error :messages="$errors->get('zona')" class="mt-1 text-xs" />
    </div>
    <div>
        <label for="orden_recorrido" class="block text-gray-700 text-sm font-bold mb-2">N.º de orden en el recorrido</label>
        <input type="number" id="orden_recorrido" name="orden_recorrido" value="{{ old('orden_recorrido', $usuario?->orden_recorrido) }}"
               min="1" max="9999" inputmode="numeric" class="{{ $clase }}{{ $claseError('orden_recorrido') }}" placeholder="Ej.: 12">
        <p class="mt-1 text-xs text-gray-500">La planilla del plomero se ordena por zona y por este número.</p>
        <x-input-error :messages="$errors->get('orden_recorrido')" class="mt-1 text-xs" />
    </div>
</div>

<div class="mb-6">
    <label for="rol_id" class="block text-gray-700 text-sm font-bold mb-2">Asignar Rol</label>
    <select id="rol_id" name="rol_id" class="{{ $clase }}{{ $claseError('rol_id') }}" style="background-color: white; color: black;" required>
        <option value="">Seleccione un rol...</option>
        @foreach($roles as $rol)
            <option value="{{ $rol->id }}" @selected((string) old('rol_id', $usuario?->rol_id) === (string) $rol->id)>{{ $rol->nombre }}</option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('rol_id')" class="mt-1 text-xs" />
</div>
