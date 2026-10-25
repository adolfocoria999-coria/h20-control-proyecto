<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Aporte (cuota extraordinaria) para una actividad: aniversario, mantenimiento,
 * compra de material... A cada socio se le registra su cuota como un cobro
 * pendiente (tabla multas, con aporte_id) que se paga por QR o en efectivo.
 */
class Aporte extends Model
{
    protected $fillable = ['titulo', 'descripcion', 'monto', 'fecha_limite', 'user_id', 'comunicado_id'];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_limite' => 'date',
        ];
    }

    public function cuotas(): HasMany
    {
        return $this->hasMany(Multa::class);
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function comunicado()
    {
        return $this->belongsTo(Comunicado::class);
    }

    /**
     * Con withCount/withSum de las cuotas: cuántos socios pagaron y cuánto se juntó.
     *
     * @return array{socios: int, pagaron: int, condonados: int, recaudado: float, esperado: float, porcentaje: int}
     */
    protected function avance(): Attribute
    {
        return Attribute::get(function () {
            $socios = (int) $this->cuotas_count;
            $condonados = (int) $this->condonados_count;
            $esperado = (float) $this->monto * ($socios - $condonados);
            $recaudado = (float) $this->recaudado;

            return [
                'socios' => $socios,
                'pagaron' => (int) $this->pagaron_count,
                'condonados' => $condonados,
                'recaudado' => $recaudado,
                'esperado' => $esperado,
                'porcentaje' => $esperado > 0 ? (int) floor($recaudado * 100 / $esperado) : 0,
            ];
        });
    }

    /**
     * Agrega a la consulta los conteos y la suma que usa avance().
     */
    public static function conAvance()
    {
        return static::query()
            ->withCount([
                'cuotas',
                'cuotas as pagaron_count' => fn ($q) => $q->where('estado', Multa::PAGADO),
                'cuotas as condonados_count' => fn ($q) => $q->where('estado', Multa::CONDONADO),
            ])
            ->withSum(['cuotas as recaudado' => fn ($q) => $q->where('estado', Multa::PAGADO)], 'monto');
    }
}
