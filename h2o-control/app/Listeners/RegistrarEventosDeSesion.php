<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Historial;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;

/**
 * Solo los eventos de sesión relevantes para la seguridad van al historial.
 * Los inicios y cierres de sesión normales no se registran: llenarían la tabla
 * sin aportar información útil.
 */
class RegistrarEventosDeSesion
{
    // Varios intentos fallidos seguidos: Laravel bloquea el acceso un momento
    public function bloqueo(Lockout $evento): void
    {
        $correo = (string) $evento->request->input('email');

        Historial::registrar(
            'bloqueo',
            'sesion',
            "Inicio de sesión bloqueado temporalmente por demasiados intentos con el correo {$correo}",
            usuario: User::where('email', $correo)->first(),
            nombreInvitado: $correo !== '' ? $correo : 'Invitado',
        );
    }

    public function contrasenaRestablecida(PasswordReset $evento): void
    {
        Historial::registrar('cambio_contrasena', 'sesion', 'Restableció su contraseña con el enlace enviado por correo', usuario: $evento->user);
    }

    public function subscribe(Dispatcher $eventos): array
    {
        return [
            Lockout::class => 'bloqueo',
            PasswordReset::class => 'contrasenaRestablecida',
        ];
    }
}
