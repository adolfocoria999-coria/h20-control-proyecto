<?php

namespace App\Support;

/**
 * Enlaces "click to chat" de WhatsApp (https://wa.me/<número>?text=<mensaje>).
 * Abren el chat con el mensaje ya escrito; el envío lo confirma quien lo abre.
 */
class WhatsApp
{
    /**
     * Número en formato internacional sin "+" ni espacios, o null si no es válido.
     * "6854-3387" → "59168543387"; "+591 68543387" → "59168543387".
     */
    public static function numero(?string $telefono): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $telefono);
        $pais = (string) config('otb.codigo_pais');

        if (strlen($digitos) === 8) {
            return $pais.$digitos;
        }

        if (str_starts_with($digitos, $pais) && strlen($digitos) === strlen($pais) + 8) {
            return $digitos;
        }

        return null;
    }

    public static function enlace(?string $telefono, string $mensaje = ''): ?string
    {
        $numero = self::numero($telefono);

        if (! $numero) {
            return null;
        }

        return "https://wa.me/{$numero}".($mensaje !== '' ? '?text='.rawurlencode($mensaje) : '');
    }
}
