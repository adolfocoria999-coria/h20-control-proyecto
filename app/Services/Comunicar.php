<?php

namespace App\Services;

use App\Models\Comunicado;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Envía una notificación de la directiva a todos los socios: queda en el
 * módulo Notificaciones y llega al celular de quienes activaron los avisos.
 */
class Comunicar
{
    public static function aTodos(User $autor, string $titulo, string $mensaje, bool $importante = false): Comunicado
    {
        $comunicado = Comunicado::create([
            'user_id' => $autor->id,
            'titulo' => trim($titulo),
            'mensaje' => trim($mensaje),
            'importante' => $importante,
        ]);

        $socios = User::socios()->get();

        Historial::registrar('creado', 'mensajes',
            'Envió la notificación "'.$comunicado->titulo.'" a '.$socios->count().' socio(s)', $comunicado, usuario: $autor);

        // Aviso en el celular (después de responder, para no demorar la pantalla)
        defer(fn () => app(NotificacionPush::class)->enviar(
            $socios,
            ($comunicado->importante ? '⚠️ ' : '📢 ').$comunicado->titulo,
            Str::limit($comunicado->mensaje, 120),
            route('mensajes.index'),
        ));

        return $comunicado;
    }
}
