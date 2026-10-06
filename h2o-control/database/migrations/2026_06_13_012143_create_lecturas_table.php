<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lecturas', function (Blueprint $table) {
            $table->id();
            // Conectamos la lectura con el socio (users)
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->string('mes');             // Ejemplo: "Enero", "Febrero"
            $table->integer('gestion');         // Ejemplo: 2026

            // Mantener como decimales puros
            $table->decimal('lectura_anterior', 8, 2);
            $table->decimal('lectura_actual', 8, 2);

            // Volvemos a añadir consumo pero como DECIMAL para que no cause errores de redondeo ni de alteración
            $table->decimal('consumo', 8, 2)->default(0.00);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lecturas');
    }
};
