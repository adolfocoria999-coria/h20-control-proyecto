<?php

namespace App\Services;

use App\Models\Lectura;

/**
 * Texto del aviso por WhatsApp al socio cuando se registra su lectura del mes:
 * consumo, monto, otras deudas de agua pendientes y cómo pagar.
 */
class AvisoLectura
{
    public static function mensaje(Lectura $lectura): string
    {
        $socio = $lectura->usuario;
        $otb = config('otb.nombre');

        // Otros meses de agua que el socio todavía debe
        $otras = Lectura::pendientes()
            ->where('user_id', $lectura->user_id)
            ->whereKeyNot($lectura->id)
            ->cronologico()
            ->get();

        $lineas = [
            "Sr(a). {$socio->name}, le saluda la {$otb}.",
            "💧 Su lectura de agua de {$lectura->mes_nombre} {$lectura->gestion} ya está registrada:",
            'Medidor: '.Lectura::m3($lectura->lectura_anterior).' → '.Lectura::m3($lectura->lectura_actual)
                .' (consumo de '.Lectura::m3($lectura->consumo).').',
            'Monto a pagar: '.self::bs($lectura->monto).'.',
        ];

        if ($otras->isNotEmpty()) {
            $lineas[] = 'Además tiene pendiente: '.$otras->map(fn (Lectura $l) => "{$l->mes_nombre} {$l->gestion} (".self::bs($l->monto).')')->join(', ', ' y ').'.';
            $lineas[] = 'Total adeudado: '.self::bs($lectura->monto + $otras->sum('monto')).'.';
        }

        $lineas[] = 'Puede pagar por QR (en el sistema H2O, "Mi Consumo") o en efectivo.';
        $lineas[] = 'Ver detalle: '.route('socio.consumo');
        $lineas[] = 'Consultas: '.config('otb.telefono_finanzas').'.';

        return implode("\n", $lineas);
    }

    private static function bs(float $monto): string
    {
        return 'Bs. '.number_format($monto, 2, ',', '.');
    }
}
