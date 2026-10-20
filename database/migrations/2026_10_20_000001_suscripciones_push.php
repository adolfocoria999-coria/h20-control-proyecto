<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Celulares (navegadores) que aceptaron recibir notificaciones del sistema.
 * Cada fila es la "dirección" de un dispositivo: menos de 1 KB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suscripciones_push', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique(); // el endpoint es largo para un índice único
            $table->string('clave_p256dh');
            $table->string('clave_auth');
            $table->string('dispositivo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suscripciones_push');
    }
};
