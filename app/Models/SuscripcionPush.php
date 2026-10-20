<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un celular o navegador de un usuario que aceptó recibir notificaciones.
 */
class SuscripcionPush extends Model
{
    protected $table = 'suscripciones_push';

    protected $fillable = ['user_id', 'endpoint', 'endpoint_hash', 'clave_p256dh', 'clave_auth', 'dispositivo'];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function hash(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
