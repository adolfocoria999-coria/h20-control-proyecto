<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Los comprobantes se guardaban en el disco `public` (accesibles por URL sin
 * iniciar sesión). Se mueven al disco privado `local`; la app los sirve por una
 * ruta protegida. La ruta relativa guardada en la base no cambia.
 */
return new class extends Migration
{
    private const COLUMNAS = [
        'balance_mensuals' => 'comprobante_url',
        'multas' => 'comprobante_pago',
    ];

    public function up(): void
    {
        $this->mover(desde: 'public', hacia: 'local');
    }

    public function down(): void
    {
        $this->mover(desde: 'local', hacia: 'public');
    }

    private function mover(string $desde, string $hacia): void
    {
        foreach (self::COLUMNAS as $tabla => $columna) {
            DB::table($tabla)->whereNotNull($columna)->pluck($columna)->each(function (string $ruta) use ($desde, $hacia) {
                if (Storage::disk($desde)->exists($ruta) && ! Storage::disk($hacia)->exists($ruta)) {
                    Storage::disk($hacia)->writeStream($ruta, Storage::disk($desde)->readStream($ruta));
                    Storage::disk($desde)->delete($ruta);
                }
            });
        }
    }
};
