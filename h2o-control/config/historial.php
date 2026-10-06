<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Retención del historial
    |--------------------------------------------------------------------------
    |
    | Meses que se conservan los registros. Con un número (por ejemplo 36), la
    | tarea diaria `model:prune` borra automáticamente lo más antiguo.
    | Vacío (por defecto) = se conserva todo y solo el superadministrador borra.
    |
    */

    'meses_retencion' => env('HISTORIAL_MESES_RETENCION'),

    'por_pagina' => 25,

];
