<?php

use App\Services\QrPago;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * El QR de pago y los comprobantes pasan del disco (public/local) a la base de
 * datos: en el hosting la carpeta storage/ se recrea en cada despliegue y se perdían.
 * Las rutas guardadas (qr/..., comprobantes/...) no cambian; los archivos que
 * todavía existan en disco se copian a la tabla.
 */
return new class extends Migration
{
    // Columna con la ruta => disco donde estaba el archivo
    private const ORIGENES = [
        ['balance_mensuals', 'comprobante_url', 'local'],
        ['multas', 'comprobante_pago', 'local'],
    ];

    public function up(): void
    {
        Schema::create('archivos', function (Blueprint $table) {
            $table->id();
            $table->string('ruta')->unique();
            $table->string('mime')->nullable();
            $table->unsignedInteger('tamano');
            $table->longText('contenido'); // base64
            $table->timestamps();
        });

        foreach (self::ORIGENES as [$tabla, $columna, $disco]) {
            DB::table($tabla)->whereNotNull($columna)->pluck($columna)
                ->each(fn (string $ruta) => $this->copiar($disco, $ruta));
        }

        $qr = DB::table('ajustes')->where('clave', QrPago::CLAVE_RUTA)->value('valor');
        if ($qr) {
            $this->copiar('public', $qr);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('archivos');
    }

    private function copiar(string $disco, string $ruta): void
    {
        if (Storage::disk($disco)->exists($ruta) && ! Storage::disk('archivos')->exists($ruta)) {
            Storage::disk('archivos')->put($ruta, Storage::disk($disco)->get($ruta));
        }
    }
};
