<?php

namespace App\Http\Controllers;

use App\Models\Comunicado;
use App\Models\User;
use App\Services\Comunicar;
use App\Services\Historial;
use Illuminate\Http\Request;

/**
 * Notificaciones: la directiva (Hacienda y presidente) envía comunicados que
 * llegan a todos los socios; cada socio los lee en el módulo Notificaciones y,
 * si activó los avisos, recibe una notificación en el celular.
 */
class MensajeController extends Controller
{
    public function index(Request $request)
    {
        $usuario = $request->user();
        $leidosHasta = $usuario->mensajes_leidos_at;

        // Al abrir Notificaciones quedan todos como leídos (sin pasar por el historial)
        User::whereKey($usuario->id)->update(['mensajes_leidos_at' => now()]);

        return view('mensajes.index', [
            'comunicados' => Comunicado::with('autor')->latest('id')->paginate(15),
            'leidosHasta' => $leidosHasta,
            'totalSocios' => User::socios()->count(),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'titulo' => 'required|string|min:3|max:120',
            'mensaje' => 'required|string|min:5|max:2000',
            'importante' => 'nullable|boolean',
        ], [], ['titulo' => 'título', 'mensaje' => 'mensaje']);

        Comunicar::aTodos($request->user(), $datos['titulo'], $datos['mensaje'], $request->boolean('importante'));

        return redirect()->route('mensajes.index')
            ->with('success', 'Notificación enviada ✓ Ya la pueden leer los '.User::socios()->count().' socios en "Notificaciones".');
    }

    public function destroy(Comunicado $comunicado)
    {
        $comunicado->delete();

        Historial::registrar('eliminado', 'mensajes', 'Eliminó la notificación "'.$comunicado->titulo.'"');

        return redirect()->route('mensajes.index')->with('success', 'Notificación eliminada.');
    }
}
