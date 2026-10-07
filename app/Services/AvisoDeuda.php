<?php

namespace App\Services;

use App\Models\User;

/**
 * Texto del aviso por WhatsApp a un socio con deuda: recordatorio, o aviso de
 * corte cuando debe 3 o más meses de agua (configurable en config/otb.php).
 *
 * El socio debe venir de ReporteFinanciero::morosos() (trae deuda_agua,
 * deuda_multas, deuda_total y periodos_mora).
 */
class AvisoDeuda
{
    public static function esCorte(User $socio): bool
    {
        return count($socio->periodos_mora ?? []) >= (int) config('otb.meses_para_corte');
    }

    public static function mensaje(User $socio): string
    {
        $otb = config('otb.nombre');
        $meses = $socio->periodos_mora ?? [];
        $cantidad = count($meses);

        $partes = [];
        if ($cantidad > 0) {
            $partes[] = ($cantidad === 1
                ? 'su consumo de agua potable de '.$meses[0]
                : "{$cantidad} meses de consumo de agua potable (".self::lista($meses).')')
                .' por '.self::bs($socio->deuda_agua);
        }
        if ($socio->deuda_multas > 0) {
            $partes[] = 'multas por '.self::bs($socio->deuda_multas);
        }

        $lineas = ["Sr(a). {$socio->name}, le saluda la {$otb}."];

        if (self::esCorte($socio)) {
            $lineas[] = 'AVISO DE CORTE: tiene pendiente el pago de '.implode(' y ', $partes).'.';
            $lineas[] = 'Total adeudado: '.self::bs($socio->deuda_total).'.';
            $lineas[] = "Al tener {$cantidad} meses de deuda corresponde el corte del servicio de agua potable. Le pedimos cancelar su deuda a la brevedad para evitarlo.";
        } else {
            $lineas[] = 'Le recordamos que debe cancelar '.implode(' y ', $partes).'.';
            if ($cantidad > 0 && $socio->deuda_multas > 0) {
                $lineas[] = 'Total adeudado: '.self::bs($socio->deuda_total).'.';
            }
            $lineas[] = 'Puede pagar por QR (desde el sistema H2O, en "Mi Consumo") o en efectivo.';
        }

        $lineas[] = 'Consultas: '.config('otb.telefono_finanzas').'.';

        return implode("\n", $lineas);
    }

    private static function bs(float $monto): string
    {
        return 'Bs. '.number_format($monto, 2, ',', '.');
    }

    // "Julio 2026, Agosto 2026 y Septiembre 2026"
    private static function lista(array $items): string
    {
        $ultimo = array_pop($items);

        return $items ? implode(', ', $items).' y '.$ultimo : (string) $ultimo;
    }
}
