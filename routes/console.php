<?php

use App\Models\Actividad;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Depuración automática del historial: solo borra si HISTORIAL_MESES_RETENCION está configurado
Schedule::command('model:prune', ['--model' => [Actividad::class]])->daily();
