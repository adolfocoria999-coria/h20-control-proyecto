<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_mensuals', function (Blueprint $table) {
            $table->id();
            $table->string('mes');
            $table->integer('gestion');
            $table->decimal('ingresos', 10, 2)->default(0);
            $table->decimal('egresos', 10, 2)->default(0);
            $table->decimal('saldo_final', 10, 2)->default(0);
            $table->text('detalle')->nullable();
            $table->string('comprobante_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_mensuals');
    }
};
