<?php

namespace App\Http\Controllers;

use App\Services\Ayuda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Asistente "💬 Ayuda": preguntas sobre cómo usar el sistema.
 */
class AyudaController extends Controller
{
    // Preguntas por usuario por hora (cuida el cupo gratuito de la IA)
    public const LIMITE_POR_HORA = 30;

    public function preguntar(Request $request)
    {
        $datos = $request->validate([
            'pregunta' => 'required|string|min:2|max:300',
            'conversacion' => 'nullable|array|max:6',
            'conversacion.*.rol' => 'required|in:usuario,asistente',
            'conversacion.*.texto' => 'required|string|max:2000',
        ]);

        $clave = 'ayuda:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($clave, self::LIMITE_POR_HORA)) {
            return response()->json([
                'respuesta' => 'Hiciste muchas preguntas en poco tiempo. Espera unos minutos y vuelve a intentar.',
                'fuentes' => [], 'modo' => 'limite',
            ], 429);
        }
        RateLimiter::hit($clave, 3600);

        return response()->json(Ayuda::responder($datos['pregunta'], $request->user(), $datos['conversacion'] ?? []));
    }
}
