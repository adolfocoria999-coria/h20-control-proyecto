<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Mensaje (comunicado) de la directiva para todos los socios.
 */
class Comunicado extends Model
{
    protected $fillable = ['user_id', 'titulo', 'mensaje', 'importante'];

    protected function casts(): array
    {
        return ['importante' => 'boolean'];
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * Cuántas notificaciones llegaron después de la última vez que el usuario abrió Notificaciones.
     */
    public static function sinLeer(User $usuario): int
    {
        return static::query()
            ->when($usuario->mensajes_leidos_at, fn ($q, $desde) => $q->where('created_at', '>', $desde))
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $usuario->id))
            ->count();
    }
}
