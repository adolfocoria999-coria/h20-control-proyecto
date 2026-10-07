<?php

namespace App\Support;

/**
 * Los meses se guardan como número (1-12) y se muestran con su nombre.
 */
class Meses
{
    public const NOMBRES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    public static function nombre(?int $numero): string
    {
        return self::NOMBRES[$numero] ?? '';
    }

    /**
     * Acepta un número ("3") o un nombre ("Marzo", "marzo ") y devuelve 1-12, o null.
     */
    public static function numero(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_numeric($valor)) {
            $n = (int) $valor;

            return $n >= 1 && $n <= 12 ? $n : null;
        }

        $buscado = mb_strtolower(trim((string) $valor));

        foreach (self::NOMBRES as $n => $nombre) {
            if (mb_strtolower($nombre) === $buscado) {
                return $n;
            }
        }

        return null;
    }
}
