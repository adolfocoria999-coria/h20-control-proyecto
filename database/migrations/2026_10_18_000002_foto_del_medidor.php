<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto del medidor tomada por el plomero en el recorrido (respaldo ante reclamos).
 * La imagen va al disco `archivos` (base de datos); aquí solo su ruta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecturas', function (Blueprint $table) {
            $table->string('foto')->nullable()->after('observacion');
        });
    }

    public function down(): void
    {
        Schema::table('lecturas', function (Blueprint $table) {
            $table->dropColumn('foto');
        });
    }
};
