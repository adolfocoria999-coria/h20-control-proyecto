@php $esAdmin = Auth::user()->esAdmin(); @endphp

<x-app-layout>
    <div class="py-10 bg-sky-200/70 min-h-screen">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div>
                <h2 class="font-extrabold text-3xl text-blue-950 leading-tight">Notificaciones</h2>
                <p class="text-sm text-sky-900 mt-1 font-bold">
                    @if($esAdmin)
                        Comunicados de la directiva para todos los socios ({{ $totalSocios }}).
                    @else
                        Comunicados de la directiva de la OTB para todos los socios.
                    @endif
                </p>
            </div>

            <x-mensajes-sesion />

            @if($esAdmin)
                <form method="POST" action="{{ route('mensajes.store') }}"
                      class="bg-white p-6 rounded-2xl shadow-xl border border-sky-100 space-y-4"
                      onsubmit="return confirm('¿Enviar esta notificación a todos los socios?')">
                    @csrf
                    <h3 class="text-lg font-black text-blue-950">📢 Nueva notificación para todos los socios</h3>

                    <div>
                        <label for="titulo" class="block text-sm font-extrabold text-blue-950 mb-1">Título</label>
                        <input type="text" name="titulo" id="titulo" maxlength="120" required value="{{ old('titulo') }}"
                               placeholder="Ej.: Asamblea general el sábado 2 de noviembre"
                               class="w-full rounded-xl border border-sky-200 px-4 py-3 focus:ring-2 focus:ring-blue-500 font-bold text-blue-900 bg-sky-50">
                        <x-input-error :messages="$errors->get('titulo')" class="mt-1 text-xs" />
                    </div>

                    <div>
                        <label for="mensaje" class="block text-sm font-extrabold text-blue-950 mb-1">Mensaje</label>
                        <textarea name="mensaje" id="mensaje" rows="5" maxlength="2000" required
                                  placeholder="Escriba aquí el comunicado: qué, cuándo, dónde..."
                                  class="w-full rounded-xl border border-sky-200 px-4 py-3 focus:ring-2 focus:ring-blue-500 text-blue-950 bg-sky-50">{{ old('mensaje') }}</textarea>
                        <x-input-error :messages="$errors->get('mensaje')" class="mt-1 text-xs" />
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <label class="inline-flex items-center gap-2 text-sm font-bold text-blue-950 cursor-pointer">
                            <input type="checkbox" name="importante" value="1" @checked(old('importante'))
                                   class="rounded border-sky-300 text-red-600 focus:ring-red-500">
                            ⚠️ Marcar como importante (corte de agua, emergencia...)
                        </label>
                        <button type="submit" class="bg-blue-700 hover:bg-blue-800 text-white px-6 py-3 rounded-xl font-extrabold text-sm shadow-md">
                            Enviar a todos los socios
                        </button>
                    </div>
                    <p class="text-xs font-bold text-sky-800">Los socios la verán en "Notificaciones" (con un número rojo en el menú) y, si activaron los avisos, también les llega al celular.</p>
                </form>
            @endif

            <div class="space-y-4">
                @forelse($comunicados as $comunicado)
                    @php $nuevo = ! $leidosHasta || $comunicado->created_at->gt($leidosHasta); @endphp
                    <article class="bg-white rounded-2xl shadow-md border p-5 {{ $comunicado->importante ? 'border-red-300 border-l-8 border-l-red-500' : 'border-sky-100 border-l-8 border-l-blue-600' }}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <h3 class="text-lg font-black text-blue-950">
                                {{ $comunicado->importante ? '⚠️' : '📢' }} {{ $comunicado->titulo }}
                                @if($nuevo && $comunicado->user_id !== Auth::id())
                                    <span class="ms-1 align-middle text-[11px] font-black uppercase bg-red-600 text-white px-2 py-0.5 rounded-full">Nuevo</span>
                                @endif
                            </h3>
                            @if($esAdmin)
                                <form method="POST" action="{{ route('mensajes.destroy', $comunicado) }}"
                                      onsubmit="return confirm('¿Eliminar esta notificación? Los socios dejarán de verla.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-extrabold text-red-700 hover:text-red-900">🗑 Eliminar</button>
                                </form>
                            @endif
                        </div>
                        <p class="mt-2 text-blue-950 whitespace-pre-line break-words">{{ $comunicado->mensaje }}</p>
                        <p class="mt-3 text-xs font-bold text-sky-800">
                            {{ $comunicado->autor?->name ?? 'Directiva' }} · {{ $comunicado->created_at->format('d/m/Y H:i') }}
                        </p>
                    </article>
                @empty
                    <div class="bg-white rounded-2xl shadow-md border border-sky-100 p-8 text-center font-bold text-sky-700">
                        Todavía no hay notificaciones de la directiva.
                    </div>
                @endforelse
            </div>

            {{ $comunicados->links() }}
        </div>
    </div>
</x-app-layout>
