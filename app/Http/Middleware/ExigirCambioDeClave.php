<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quien ingresa con una contraseña temporal solo puede ir a "Mi perfil" a
 * cambiarla (o cerrar sesión) hasta que lo haga.
 */
class ExigirCambioDeClave
{
    private const PERMITIDAS = ['profile.edit', 'password.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario?->debe_cambiar_clave && ! $request->routeIs(...self::PERMITIDAS)) {
            return redirect()->route('profile.edit')
                ->with('error', 'Estás usando una contraseña temporal: elige una nueva para continuar.');
        }

        return $next($request);
    }
}
