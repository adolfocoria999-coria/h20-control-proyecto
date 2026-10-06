<?php

namespace App\Providers;

use App\Listeners\RegistrarEventosDeSesion;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // URLs de los resource en español: /lecturas/crear, /multas/{id}/editar
        Route::resourceVerbs([
            'create' => 'crear',
            'edit' => 'editar',
        ]);

        // Sesiones (inicio, cierre, intentos fallidos) al historial de actividades
        Event::subscribe(RegistrarEventosDeSesion::class);
    }
}
