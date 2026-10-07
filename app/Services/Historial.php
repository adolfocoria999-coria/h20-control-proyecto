<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/**
 * Punto único para escribir en el historial de actividades.
 */
class Historial
{
    /**
     * @param  array<string, mixed>  $cambios
     */
    public static function registrar(
        string $accion,
        string $modulo,
        string $descripcion,
        ?Model $sujeto = null,
        array $cambios = [],
        ?User $usuario = null,
        ?string $nombreInvitado = null,
    ): void {
        $usuario ??= auth()->user();

        // Seeders, migraciones y tinker (sin usuario) no se registran
        if (! $usuario && ! $nombreInvitado && app()->runningInConsole()) {
            return;
        }

        try {
            $rol = $usuario?->rolUsuario();

            Actividad::create([
                'user_id' => $usuario?->getKey(),
                'usuario_nombre' => $usuario?->name ?? $nombreInvitado ?? 'Invitado',
                'rol' => $rol?->alias(),
                'seccion' => $usuario?->esAdmin() ? Actividad::SECCION_ADMIN : Actividad::SECCION_USUARIO,
                'accion' => $accion,
                'modulo' => $modulo,
                'descripcion' => Str::limit($descripcion, 497),
                'sujeto_type' => $sujeto?->getMorphClass(),
                'sujeto_id' => $sujeto?->getKey(),
                'cambios' => $cambios ?: null,
                'ip' => request()?->ip(),
                'user_agent' => Str::limit((string) request()?->userAgent(), 252) ?: null,
            ]);
        } catch (Throwable $e) {
            // Un fallo del historial nunca debe impedir la operación del usuario
            report($e);
        }
    }
}
