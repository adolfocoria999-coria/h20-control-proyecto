<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuándo se avisó al socio por WhatsApp que su lectura del mes está lista
 * (para saber a quién falta avisar y no mandarlo dos veces).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecturas', function (Blueprint $table) {
            $table->timestamp('avisado_at')->nullable()->after('foto');
        });
    }

    public function down(): void
    {
        Schema::table('lecturas', function (Blueprint $table) {
            $table->dropColumn('avisado_at');
        });
    }
};
