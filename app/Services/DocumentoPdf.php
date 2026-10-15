<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Genera los PDF de la OTB (recibos, historial de pagos, arqueo) con dompdf.
 * Las vistas están en resources/views/pdf y usan pdf/layout.
 */
class DocumentoPdf
{
    public static function descargar(string $vista, array $datos, string $nombreArchivo, string $orientacion = 'portrait'): Response
    {
        return Pdf::loadView($vista, $datos)
            ->setPaper('a4', $orientacion)
            // DejaVu Sans trae tildes, ñ y símbolos; nada se descarga de internet
            // Solo se incrustan las letras usadas: el PDF pesa decenas de KB y no cientos
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isFontSubsettingEnabled' => true])
            ->download($nombreArchivo);
    }
}
