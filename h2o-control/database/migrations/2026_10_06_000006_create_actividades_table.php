<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de actividades. Solo se insertan filas (no hay updated_at).
 * Los índices empiezan por la columna de filtro y terminan en created_at:
 * las consultas por sección/usuario/módulo + rango de fechas siguen siendo
 * rápidas aunque la tabla crezca a cientos de miles de filas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Copia del nombre y rol al momento de la acción: el historial no cambia si luego se edita el usuario
            $table->string('usuario_nombre')->nullable();
            $table->string('rol', 20)->nullable();
            $table->string('seccion', 10);        // admin | usuario
            $table->string('accion', 30);
            $table->string('modulo', 30);
            $table->string('descripcion', 500);
            $table->nullableMorphs('sujeto');      // registro afectado (lectura, multa, ...)
            $table->json('cambios')->nullable();   // {campo: {antes, despues}}
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['seccion', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['modulo', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividades');
    }
};
