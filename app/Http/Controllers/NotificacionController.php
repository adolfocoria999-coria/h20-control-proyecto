<?php

namespace App\Http\Controllers;

use App\Models\SuscripcionPush;
use App\Services\Historial;
use App\Services\NotificacionPush;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Activar o desactivar las notificaciones en el celular del usuario.
 */
class NotificacionController extends Controller
{
    public function suscribir(Request $request)
    {
        $datos = $request->validate([
            'endpoint' => 'required|url:https|max:2000',
            'keys.p256dh' => 'required|string|max:255',
            'keys.auth' => 'required|string|max:255',
        ]);

        $nueva = ! SuscripcionPush::where('endpoint_hash', SuscripcionPush::hash($datos['endpoint']))->exists();

        // Si el celular estaba asociado a otro usuario (otra sesión), pasa al actual
        SuscripcionPush::updateOrCreate(
            ['endpoint_hash' => SuscripcionPush::hash($datos['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $datos['endpoint'],
                'clave_p256dh' => $datos['keys']['p256dh'],
                'clave_auth' => $datos['keys']['auth'],
                'dispositivo' => Str::limit((string) $request->userAgent(), 250),
            ]
        );

        if ($nueva) {
            Historial::registrar('actualizado', 'usuarios', 'Activó las notificaciones en su celular');
        }

        return response()->json(['activadas' => true]);
    }

    public function desuscribir(Request $request)
    {
        $request->validate(['endpoint' => 'required|string|max:2000']);

        SuscripcionPush::where('user_id', $request->user()->id)
            ->where('endpoint_hash', SuscripcionPush::hash($request->input('endpoint')))
            ->delete();

        return response()->json(['activadas' => false]);
    }

    public function probar(Request $request, NotificacionPush $push)
    {
        $resultado = $push->enviar($request->user(), '🔔 Notificaciones activadas',
            'Así te avisaremos cuando se registre tu lectura de agua o la directiva envíe una notificación.', route('socio.consumo'));

        return response()->json($resultado, $resultado['enviadas'] ? 200 : 422);
    }
}
