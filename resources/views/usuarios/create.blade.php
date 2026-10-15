<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Registrar Nuevo Socio') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    
                    <form action="{{ route('usuarios.store') }}" method="POST">
                        @csrf
                        
                        @include('usuarios._campos', ['usuario' => null])

                        <div class="flex items-center justify-end" style="margin-top: 20px; gap: 15px;">
    <a href="{{ route('usuarios.index') }}" style="background-color: #6b7280; color: white; padding: 10px 20px; border-radius: 6px; font-weight: bold; text-decoration: none; box-shadow: 0px 4px 6px rgba(0,0,0,0.1);">
        Cancelar
    </a>
    <button type="submit" style="background-color: #16a34a; color: white; padding: 10px 20px; border-radius: 6px; font-weight: bold; border: none; cursor: pointer; box-shadow: 0px 4px 6px rgba(0,0,0,0.1);">
        Guardar Socio
    </button>
</div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>