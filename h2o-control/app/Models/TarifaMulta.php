<?php

namespace App\Models;

use App\Models\Concerns\RegistraEnHistorial;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TarifaMulta extends Model
{
    use HasFactory, RegistraEnHistorial;

    protected $table = 'tarifas_multas';

    protected $fillable = [
        'nombre',
        'monto_predeterminado',
        'descripcion',
    ];

    protected function casts(): array
    {
        return [
            'monto_predeterminado' => 'decimal:2',
        ];
    }

    // ---------- Historial ----------

    protected function moduloHistorial(): string
    {
        return 'tarifas';
    }

    protected function etiquetaHistorial(): string
    {
        return "la tarifa «{$this->nombre}» (Bs. ".number_format((float) $this->monto_predeterminado, 2, ',', '.').')';
    }
}
