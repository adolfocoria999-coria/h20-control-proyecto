<?php

use App\Exceptions\RespaldoInvalido;
use App\Models\Actividad;
use App\Services\Respaldo;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Respaldos desde la consola (para archivos grandes o restaurar en otra máquina)
Artisan::command('respaldo:crear {ruta? : Archivo de salida (.json.gz)}', function (?string $ruta = null) {
    $ruta ??= storage_path('app/respaldo_h2o_'.now()->format('Y-m-d_Hi').'.json.gz');
    $conteos = Respaldo::generar($ruta, 'consola');
    $this->info('Respaldo creado: '.$ruta.' ('.array_sum($conteos).' registros)');
})->purpose('Crea un respaldo completo de la base de datos');

Artisan::command('respaldo:restaurar {archivo : Respaldo .json.gz} {--force : No pedir confirmación}', function (string $archivo) {
    try {
        $datos = Respaldo::leer($archivo);
    } catch (RespaldoInvalido $e) {
        $this->error($e->getMessage());

        return 1;
    }

    $this->warn("Respaldo del {$datos['creado']} hecho por {$datos['por']}.");
    if (! $this->option('force') && ! $this->confirm('Se reemplazarán TODOS los datos actuales. ¿Continuar?')) {
        return 1;
    }

    $restauradas = Respaldo::restaurar($datos);
    $this->info('Restaurado: '.array_sum($restauradas).' registros.');
})->purpose('Restaura un respaldo (reemplaza todos los datos)');

// Depuración automática del historial: solo borra si HISTORIAL_MESES_RETENCION está configurado
Schedule::command('model:prune', ['--model' => [Actividad::class]])->daily();
