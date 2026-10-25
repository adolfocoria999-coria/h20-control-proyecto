@php
    // Notificaciones de la directiva que el usuario todavía no abrió (no aplica al plomero)
    $mensajesSinLeer = Auth::user()->esPlomero() ? 0 : \App\Models\Comunicado::sinLeer(Auth::user());
@endphp
<nav x-data="{ open: false }" class="bg-white border-b border-gray-100 print:hidden">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex flex-1">
                <div class="shrink-0 flex items-center">
                    <a href="{{ Auth::user()->rutaInicio() }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <div class="hidden flex-1 justify-center space-x-5 2xl:space-x-8 xl:-my-px xl:mx-8 xl:flex">
                    
                    <!-- 1. VISTA EXCLUSIVA PARA SUPERADMIN -->
                    @if(Auth::user()->esSuperAdmin())
                        <x-nav-link :href="route('usuarios.index')" :active="request()->routeIs('usuarios.*')">
                            {{ __('Socios') }}
                        </x-nav-link>
                        <x-nav-link :href="route('tarifa-agua.index')" :active="request()->routeIs('tarifa-agua.*')">
                            {{ __('Tarifa') }}
                        </x-nav-link>
                    @endif

                    <!-- 2. VISTAS PARA SUPERADMIN Y ADMIN/HACIENDA (1 y 2) -->
                    @if(Auth::user()->esAdmin())
                        <x-nav-link :href="route('lecturas.index')" :active="request()->routeIs('lecturas.*')">
                            {{ __('Lecturas de Agua') }}
                        </x-nav-link>
                    @endif
                    
                    <!-- 2b. PLOMERO: solo la carga de lecturas del recorrido -->
                    @if(Auth::user()->esPlomero())
                        <x-nav-link :href="route('lecturas.planilla')" :active="request()->routeIs('lecturas.planilla*')">
                            {{ __('Carga de Lecturas') }}
                        </x-nav-link>
                    @endif

                    <!-- 3. VISTA EXCLUSIVA DE CONSUMO PARA SOCIO (3) -->
                    @if(Auth::user()->esSocio())
                        <x-nav-link :href="route('socio.consumo')" :active="request()->routeIs('socio.consumo')">
                            {{ __('Mi Consumo de Agua') }}
                        </x-nav-link>
                        <x-nav-link :href="route('socio.pagos')" :active="request()->routeIs('socio.pagos*')">
                            {{ __('Mis Pagos') }}
                        </x-nav-link>
                    @endif

                    <!-- 4. VISTAS COMPARTIDAS (Finanzas, Multas y Sanciones): no para el plomero -->
                    @unless(Auth::user()->esPlomero())
                        <x-nav-link :href="route('finanzas.index')" :active="request()->routeIs('finanzas.*')">
                            {{ __('Finanzas') }}
                        </x-nav-link>

                        <x-nav-link :href="route('multas.index')" :active="request()->routeIs('multas.*')">
                            {{ __('Multas y Sanciones') }}
                        </x-nav-link>

                        <x-nav-link :href="route('mensajes.index')" :active="request()->routeIs('mensajes.*')">
                            {{ __('Notificaciones') }}
                            @if($mensajesSinLeer > 0)
                                <span class="ms-1.5 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-red-600 text-white text-[11px] font-black" title="Notificaciones sin leer">{{ $mensajesSinLeer }}</span>
                            @endif
                        </x-nav-link>
                    @endunless

                    <!-- 5. NUEVO MÓDULO DE REPORTES (Solo Admin/SuperAdmin) -->
                    @if(Auth::user()->esAdmin())
                        <x-nav-link :href="route('reportes.index')" :active="request()->routeIs('reportes.*')">
                            {{ __('Reportes') }}
                        </x-nav-link>
                        <x-nav-link :href="route('historial.index')" :active="request()->routeIs('historial.*')">
                            {{ __('Historial') }}
                        </x-nav-link>
                        <x-nav-link :href="route('respaldos.index')" :active="request()->routeIs('respaldos.*')">
                            {{ __('Respaldos') }}
                        </x-nav-link>
                    @endif

                </div>
            </div>

            <div class="hidden xl:flex xl:items-center">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div class="whitespace-nowrap max-w-[11rem] truncate" title="{{ Auth::user()->name }}">{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>
                        <x-dropdown-link href="#" class="opcion-instalar" onclick="event.preventDefault(); instalarApp();">
                            📲 Instalar app
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center xl:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- MENÚ MÓVIL RESPONSIVO -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden xl:hidden">
        <div class="pt-2 pb-3 space-y-1">
            
            @if(Auth::user()->esSuperAdmin())
                <x-responsive-nav-link :href="route('usuarios.index')" :active="request()->routeIs('usuarios.*')">
                    {{ __('Socios') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('tarifa-agua.index')" :active="request()->routeIs('tarifa-agua.*')">
                    {{ __('Tarifa del agua') }}
                </x-responsive-nav-link>
            @endif

            @if(Auth::user()->esAdmin())
                <x-responsive-nav-link :href="route('lecturas.index')" :active="request()->routeIs('lecturas.*')">
                    {{ __('Lecturas de Agua') }}
                </x-responsive-nav-link>
            @endif

            @if(Auth::user()->esPlomero())
                <x-responsive-nav-link :href="route('lecturas.planilla')" :active="request()->routeIs('lecturas.planilla*')">
                    {{ __('Carga de Lecturas') }}
                </x-responsive-nav-link>
            @endif

            @if(Auth::user()->esSocio())
                <x-responsive-nav-link :href="route('socio.consumo')" :active="request()->routeIs('socio.consumo')">
                    {{ __('Mi Consumo de Agua') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('socio.pagos')" :active="request()->routeIs('socio.pagos*')">
                    {{ __('Mis Pagos') }}
                </x-responsive-nav-link>
            @endif

            <!-- Finanzas y Multas: todos menos el plomero -->
            @unless(Auth::user()->esPlomero())
                <x-responsive-nav-link :href="route('finanzas.index')" :active="request()->routeIs('finanzas.*')">
                    {{ __('Finanzas') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('multas.index')" :active="request()->routeIs('multas.*')">
                    {{ __('Multas y Sanciones') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('mensajes.index')" :active="request()->routeIs('mensajes.*')">
                    {{ __('Notificaciones') }}
                    @if($mensajesSinLeer > 0)
                        <span class="ms-1.5 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-red-600 text-white text-[11px] font-black">{{ $mensajesSinLeer }}</span>
                    @endif
                </x-responsive-nav-link>
            @endunless

            <!-- Reportes para el menú móvil -->
            @if(Auth::user()->esAdmin())
                <x-responsive-nav-link :href="route('reportes.index')" :active="request()->routeIs('reportes.*')">
                    {{ __('Reportes') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('historial.index')" :active="request()->routeIs('historial.*')">
                    {{ __('Historial') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('respaldos.index')" :active="request()->routeIs('respaldos.*')">
                    {{ __('Respaldos') }}
                </x-responsive-nav-link>
            @endif

        </div>

        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link href="#" class="opcion-instalar" onclick="event.preventDefault(); instalarApp();">
                    📲 Instalar app
                </x-responsive-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>