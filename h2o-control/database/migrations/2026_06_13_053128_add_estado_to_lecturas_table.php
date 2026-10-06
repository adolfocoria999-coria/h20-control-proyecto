<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecturas', function (Blueprint $table) {
            // Toda lectura nace como 'pendiente' por defecto
            $table->string('estado')->default('pendiente')->after('consumo');
        });
    }

    public function down(): void
    {
        Schema::table('lecturas', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
