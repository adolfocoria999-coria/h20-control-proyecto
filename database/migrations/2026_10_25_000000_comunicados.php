<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mensajes (comunicados) de la directiva a todos los socios, y hasta cuándo
 * leyó cada usuario (para mostrar cuántos tiene sin leer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comunicados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titulo', 120);
            $table->text('mensaje');
            $table->boolean('importante')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('mensajes_leidos_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mensajes_leidos_at');
        });
        Schema::dropIfExists('comunicados');
    }
};
