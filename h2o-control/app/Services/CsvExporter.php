<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga CSV compatible con Excel en español: UTF-8 con BOM y separador ';'.
 */
class CsvExporter
{
    /**
     * @param  iterable<array<int, mixed>>  $filas  Puede ser una LazyCollection para no cargar todo en memoria.
     */
    public static function descargar(string $nombreArchivo, array $encabezados, iterable $filas): StreamedResponse
    {
        return response()->streamDownload(function () use ($encabezados, $filas) {
            // Descarta salida residual (espacios, avisos) para prevenir descargas corruptas
            if (ob_get_level() > 0) {
                ob_clean();
            }

            $file = fopen('php://output', 'w');

            // BOM UTF-8 para que Excel muestre bien tildes y ñ
            fwrite($file, "\xEF\xBB\xBF");

            // Le indica a Excel que el delimitador de columnas es ';'
            fwrite($file, "sep=;\n");

            fputcsv($file, $encabezados, ';');

            foreach ($filas as $fila) {
                fputcsv($file, $fila, ';');
            }

            fclose($file);
        }, $nombreArchivo, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    // Formato de dinero para Excel en español: "1234,50"
    public static function dinero(float|string|null $monto): string
    {
        return number_format((float) $monto, 2, ',', '');
    }
}
