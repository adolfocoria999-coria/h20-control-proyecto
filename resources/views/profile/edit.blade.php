<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-mensajes-sesion />

            @if(auth()->user()->debe_cambiar_clave)
                <div class="p-4 bg-amber-50 border-l-4 border-amber-500 rounded-r-xl shadow-md text-sm font-bold text-amber-900">
                    🔑 Estás usando una contraseña temporal. Escríbela en "Contraseña actual" y elige una nueva para seguir usando el sistema.
                </div>
            @endif

            <x-avisos-celular />

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
