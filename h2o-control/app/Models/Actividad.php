<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Un registro del historial: quién hizo qué, cuándo y desde dónde.
 */
class Actividad extends Model
{
    use HasFactory, MassPrunable;

    protected $table = 'actividades';

    // El historial no se edita: solo tiene fecha de creación
    public const UPDATED_AT = null;

    public const SECCION_ADMIN = 'admin';

    public const SECCION_USUARIO = 'usuario';

    // Acción => verbo que encabeza la descripción y etiqueta del filtro
    public const ACCIONES = [
        'creado' => 'Registró',
        'actualizado' => 'Actualizó',
        'eliminado' => 'Eliminó',
        'pago' => 'Registró el pago de',
        'condonacion' => 'Condonó',
        'baja' => 'Dio de baja',
        'restaurado' => 'Restauró',
        'exportacion' => 'Exportó',
        'notificacion' => 'Notificó',
        'bloqueo' => 'Bloqueo por intentos',
        'cambio_contrasena' => 'Restableció contraseña',
    ];

    public const MODULOS = [
        'sesion' => 'Sesión',
        'usuarios' => 'Socios',
        'lecturas' => 'Lecturas',
        'multas' => 'Multas',
        'tarifas' => 'Tarifas de multas',
        'finanzas' => 'Finanzas',
        'qr' => 'QR de pago',
        'reportes' => 'Reportes',
        'historial' => 'Historial',
    ];

    protected $fillable = [
        'user_id',
        'usuario_nombre',
        'rol',
        'seccion',
        'accion',
        'modulo',
        'descripcion',
        'sujeto_type',
        'sujeto_id',
        'cambios',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'cambios' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function sujeto(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeSeccion(Builder $query, string $seccion): Builder
    {
        return $query->where('seccion', $seccion);
    }

    /**
     * Filtra por año y, opcionalmente, mes. Usa un rango de fechas (no YEAR()/MONTH())
     * para que la base de datos pueda usar los índices sobre created_at.
     */
    public function scopePeriodo(Builder $query, int $gestion, ?int $mes = null): Builder
    {
        $desde = $mes ? Carbon::create($gestion, $mes, 1)->startOfMonth() : Carbon::create($gestion, 1, 1)->startOfYear();
        $hasta = $mes ? $desde->copy()->endOfMonth() : $desde->copy()->endOfYear();

        return $query->whereBetween('created_at', [$desde, $hasta]);
    }

    /**
     * Depuración automática (php artisan model:prune), solo si se configuró
     * HISTORIAL_MESES_RETENCION. Sin configurar, no borra nada.
     */
    public function prunable(): Builder
    {
        $meses = (int) config('historial.meses_retencion');

        return $meses > 0
            ? static::where('created_at', '<', now()->subMonths($meses)->startOfMonth())
            : static::whereRaw('1 = 0');
    }

    public function accionEtiqueta(): string
    {
        return match ($this->accion) {
            'creado' => 'Creación',
            'actualizado' => 'Edición',
            'eliminado' => 'Eliminación',
            'pago' => 'Pago',
            'condonacion' => 'Condonación',
            'baja' => 'Baja',
            'restaurado' => 'Restauración',
            'exportacion' => 'Exportación',
            'notificacion' => 'Aviso por WhatsApp',
            default => self::ACCIONES[$this->accion] ?? $this->accion,
        };
    }

    public function moduloEtiqueta(): string
    {
        return self::MODULOS[$this->modulo] ?? ucfirst($this->modulo);
    }
}
