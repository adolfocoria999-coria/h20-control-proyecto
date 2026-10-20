<?php

namespace App\Services;

use App\Exceptions\RespaldoInvalido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Respaldo completo de la base de datos en un archivo que se descarga al
 * dispositivo del administrador (no ocupa espacio en el servidor).
 *
 * Formato: JSON comprimido con gzip (.json.gz). Incluye todas las tablas con
 * datos del sistema —socios, lecturas, pagos, multas, finanzas, historial y los
 * archivos (QR, comprobantes, fotos), que viven en la tabla `archivos`—; deja
 * afuera solo lo temporal (sesiones, caché, colas).
 */
class Respaldo
{
    public const FORMATO = 'h2o-control-respaldo';

    public const VERSION = 1;

    // Datos temporales: no se respaldan ni se tocan al restaurar
    public const TABLAS_EXCLUIDAS = [
        'migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches',
        'failed_jobs', 'password_reset_tokens',
    ];

    /**
     * @return list<string>
     */
    public static function tablas(): array
    {
        $tablas = Schema::getTableListing(Schema::getCurrentSchemaName(), false);

        return array_values(array_filter($tablas, fn ($t) => ! in_array($t, self::TABLAS_EXCLUIDAS, true)));
    }

    /**
     * Escribe el respaldo en $ruta (gzip) fila por fila, sin cargar todo en memoria.
     *
     * @return array<string, int> filas por tabla
     */
    public static function generar(string $ruta, string $autor): array
    {
        $gz = gzopen($ruta, 'wb6');
        if (! $gz) {
            throw new RuntimeException('No se pudo crear el archivo de respaldo.');
        }

        $json = fn ($valor) => json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $conteos = [];

        gzwrite($gz, '{"formato":'.$json(self::FORMATO).',"version":'.self::VERSION
            .',"sistema":'.$json(config('app.name'))
            .',"otb":'.$json(config('otb.nombre'))
            .',"creado":'.$json(now()->toIso8601String())
            .',"por":'.$json($autor)
            .',"migraciones":'.$json(DB::table('migrations')->orderBy('id')->pluck('migration'))
            .',"tablas":{');

        foreach (self::tablas() as $i => $tabla) {
            gzwrite($gz, ($i ? ',' : '').$json($tabla).':[');
            $conteos[$tabla] = 0;

            foreach (DB::table($tabla)->cursor() as $fila) {
                gzwrite($gz, ($conteos[$tabla] ? ',' : '').$json($fila));
                $conteos[$tabla]++;
            }

            gzwrite($gz, ']');
        }

        gzwrite($gz, '},"conteos":'.$json((object) $conteos).'}');
        gzclose($gz);

        return $conteos;
    }

    /**
     * Lee y valida un respaldo. Devuelve los datos listos para restaurar.
     */
    public static function leer(string $ruta): array
    {
        $contenido = @file_get_contents($ruta);
        $texto = $contenido !== false ? @gzdecode($contenido) : false;
        $datos = $texto !== false ? json_decode($texto, true) : null;

        if (! is_array($datos) || ($datos['formato'] ?? null) !== self::FORMATO || ! is_array($datos['tablas'] ?? null)) {
            throw new RespaldoInvalido('El archivo no es un respaldo de H2O Control (o está dañado).');
        }

        // Un respaldo de una versión más nueva del sistema no encaja en esta base
        $actuales = DB::table('migrations')->pluck('migration')->all();
        $faltan = array_diff($datos['migraciones'] ?? [], $actuales);
        if ($faltan) {
            throw new RespaldoInvalido('El respaldo es de una versión más nueva del sistema: actualiza el sistema antes de restaurarlo.');
        }

        return $datos;
    }

    /**
     * Reemplaza TODOS los datos por los del respaldo, en una sola transacción:
     * si algo falla, la base queda como estaba.
     *
     * @return array<string, int> filas restauradas por tabla
     */
    public static function restaurar(array $datos): array
    {
        // Padres antes que hijos (rols → users → lecturas...): se borra al revés y se
        // inserta en orden, así se respetan las claves foráneas en cualquier motor
        $tablasActuales = self::ordenarPorDependencias(self::tablas());
        $restauradas = [];

        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use ($datos, $tablasActuales, &$restauradas) {
                foreach (array_reverse($tablasActuales) as $tabla) {
                    DB::table($tabla)->delete();
                }

                foreach ($tablasActuales as $tabla) {
                    $filas = $datos['tablas'][$tabla] ?? null;
                    if (! is_array($filas)) {
                        continue; // tabla nueva que el respaldo todavía no tenía
                    }

                    // Solo las columnas que existen hoy (un respaldo anterior puede tener menos)
                    $columnas = array_flip(Schema::getColumnListing($tabla));
                    $restauradas[$tabla] = 0;

                    foreach (array_chunk($filas, 200) as $lote) {
                        // Un insert múltiple exige las mismas columnas en cada fila: se agrupan por columnas
                        $grupos = [];
                        foreach ($lote as $fila) {
                            $fila = array_intersect_key((array) $fila, $columnas);
                            ksort($fila);
                            $grupos[implode(',', array_keys($fila))][] = $fila;
                        }
                        foreach ($grupos as $grupo) {
                            DB::table($tabla)->insert($grupo);
                        }
                        $restauradas[$tabla] += count($lote);
                    }
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $restauradas;
    }

    /**
     * Ordena las tablas para que cada una vaya después de las que referencia.
     *
     * @param  list<string>  $tablas
     * @return list<string>
     */
    private static function ordenarPorDependencias(array $tablas): array
    {
        $depende = [];
        foreach ($tablas as $tabla) {
            $depende[$tabla] = array_values(array_unique(array_filter(
                array_column(Schema::getForeignKeys($tabla), 'foreign_table'),
                fn ($padre) => $padre !== $tabla && in_array($padre, $tablas, true)
            )));
        }

        $orden = [];
        $visitar = function (string $tabla, array $camino = []) use (&$visitar, &$orden, $depende) {
            if (in_array($tabla, $orden, true) || in_array($tabla, $camino, true)) {
                return; // ya ubicada, o ciclo (no hay en este sistema)
            }
            foreach ($depende[$tabla] as $padre) {
                $visitar($padre, [...$camino, $tabla]);
            }
            $orden[] = $tabla;
        };

        foreach ($tablas as $tabla) {
            $visitar($tabla);
        }

        return $orden;
    }
}
