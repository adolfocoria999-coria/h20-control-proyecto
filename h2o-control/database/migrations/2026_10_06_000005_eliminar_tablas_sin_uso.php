<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `medidors` y `deudas` se crearon vacías (solo id y fechas) y nunca se usaron:
 * las deudas salen de lecturas y multas pendientes. Se eliminan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('medidors');
        Schema::dropIfExists('deudas');
    }

    public function down(): void
    {
        foreach (['medidors', 'deudas'] as $tabla) {
            Schema::create($tabla, function (Blueprint $table) {
                $table->id();
                $table->timestamps();
            });
        }
    }
};
