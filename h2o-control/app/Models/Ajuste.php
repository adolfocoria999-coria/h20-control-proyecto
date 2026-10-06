<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuración clave/valor del sistema (tabla `ajustes`).
 */
class Ajuste extends Model
{
    public const TARIFA_M3 = 'tarifa_m3';

    public const TARIFA_M3_POR_DEFECTO = 2.00;

    protected $fillable = [
        'clave',
        'valor',
    ];

    public static function valor(string $clave, mixed $porDefecto = null): mixed
    {
        return static::where('clave', $clave)->value('valor') ?? $porDefecto;
    }

    public static function guardar(string $clave, string $valor): void
    {
        static::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
    }

    /**
     * Precio en Bs. por cada m³ consumido.
     */
    public static function tarifaM3(): float
    {
        return once(fn () => (float) static::valor(self::TARIFA_M3, self::TARIFA_M3_POR_DEFECTO));
    }
}
