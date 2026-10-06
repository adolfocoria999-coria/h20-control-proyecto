<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * El formulario anterior guardaba el QR en el disco privado (`public/qr_oficial.png`
 * dentro de storage/app/private), por eso los socios nunca lo veían.
 * Se copia al disco `public` y se registra su ruta en `ajustes`. Queda sin fecha
 * de vencimiento: el sistema pedirá a Hacienda que la cargue.
 */
return new class extends Migration
{
    private const ORIGEN = 'public/qr_oficial.png';

    private const DESTINO = 'qr/qr_otb_migrado.png';

    public function up(): void
    {
        if (DB::table('ajustes')->where('clave', 'qr_ruta')->exists()
            || ! Storage::disk('local')->exists(self::ORIGEN)) {
            return;
        }

        Storage::disk('public')->writeStream(self::DESTINO, Storage::disk('local')->readStream(self::ORIGEN));
        Storage::disk('local')->delete(self::ORIGEN);

        DB::table('ajustes')->insert([
            'clave' => 'qr_ruta',
            'valor' => self::DESTINO,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Storage::disk('public')->exists(self::DESTINO)) {
            Storage::disk('local')->writeStream(self::ORIGEN, Storage::disk('public')->readStream(self::DESTINO));
            Storage::disk('public')->delete(self::DESTINO);
        }

        DB::table('ajustes')->whereIn('clave', ['qr_ruta', 'qr_vence_el'])->delete();
    }
};
