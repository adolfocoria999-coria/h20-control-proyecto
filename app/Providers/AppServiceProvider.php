<?php

namespace App\Providers;

use App\Listeners\RegistrarEventosDeSesion;
use App\Support\ArchivosEnBaseDeDatos;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

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

        // Disco `archivos`: guarda los archivos subidos en la base de datos
        Storage::extend('base_de_datos', function ($app, array $config) {
            $adapter = new ArchivosEnBaseDeDatos;

            return new FilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
        });

        // Sesiones (inicio, cierre, intentos fallidos) al historial de actividades
        Event::subscribe(RegistrarEventosDeSesion::class);

        // Fuera de la PC de desarrollo (hosting de pruebas o producción) todas las URLs usan https
        if (! $this->app->environment('local', 'testing')) {
            URL::forceScheme('https');
        }
    }
}
