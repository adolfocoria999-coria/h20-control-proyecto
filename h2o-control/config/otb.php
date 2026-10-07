<?php

return [

    // Nombre de la organización tal como aparece en los avisos a los socios
    'nombre' => env('OTB_NOMBRE', 'OTB Chulla Jayata'),

    // Teléfono (WhatsApp) del Secretario de Finanzas para consultas de los socios
    'telefono_finanzas' => env('OTB_TELEFONO_FINANZAS', '63883052'),

    // Código de país que se antepone a los celulares de 8 dígitos (Bolivia)
    'codigo_pais' => env('OTB_CODIGO_PAIS', '591'),

    // Meses de deuda de agua a partir de los cuales corresponde el corte
    'meses_para_corte' => (int) env('OTB_MESES_PARA_CORTE', 3),

    // Superadministrador inicial que crea `php artisan db:seed` (solo la primera vez)
    'admin' => [
        'nombre' => env('ADMIN_NOMBRE', 'Superadministrador'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
