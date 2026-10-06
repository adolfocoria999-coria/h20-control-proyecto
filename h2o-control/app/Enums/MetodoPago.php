<?php

namespace App\Enums;

/**
 * Cómo pagó el socio una lectura o una multa.
 */
enum MetodoPago: string
{
    case Qr = 'qr';
    case Efectivo = 'efectivo';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Qr => 'QR',
            self::Efectivo => 'Efectivo',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Qr => '📱',
            self::Efectivo => '💵',
        };
    }
}
