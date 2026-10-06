<?php

use App\Http\Controllers\ArqueoController;
use App\Http\Controllers\BalanceMensualController;
use App\Http\Controllers\HistorialController;
use App\Http\Controllers\LecturaController;
use App\Http\Controllers\MisPagosController;
use App\Http\Controllers\MultaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QrPagoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\TarifaMultaController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// REDIRECCIÓN AUTOMÁTICA AL LOGIN AL INGRESAR A LA RAÍZ (/)
Route::get('/', function () {
    return redirect()->route('login');
});

// DASHBOARD: cada rol va a su pantalla de inicio
Route::get('/dashboard', function () {
    return redirect(auth()->user()->rutaInicio());
})->middleware('auth')->name('dashboard');

// ==========================================================
// SOLO SUPERADMIN
// ==========================================================
Route::middleware(['auth', 'role:superadmin'])->group(function () {

    // Gestión de socios (la exportación va antes del resource)
    Route::get('/usuarios/exportar', [UserController::class, 'exportar'])->name('usuarios.exportar');
    Route::resource('usuarios', UserController::class)->except('show');

    // Historial: solo el superadministrador puede borrar registros
    Route::delete('/historial/periodo', [HistorialController::class, 'destroyPeriodo'])->name('historial.destroy-periodo');
    Route::delete('/historial/{actividad}', [HistorialController::class, 'destroy'])->name('historial.destroy');
});

// ==========================================================
// SUPERADMIN Y ADMIN (HACIENDA)
// ==========================================================
Route::middleware(['auth', 'role:superadmin,admin'])->group(function () {

    // EXPORTACIONES (SIEMPRE VAN ANTES DE LOS RESOURCE)
    Route::get('/lecturas/exportar', [LecturaController::class, 'exportar'])->name('lecturas.exportar');
    Route::get('/reportes/exportar', [ReporteController::class, 'exportar'])->name('reportes.exportar');

    // HISTORIAL DE ACTIVIDADES (consulta)
    Route::get('/historial/exportar', [HistorialController::class, 'exportar'])->name('historial.exportar');
    Route::get('/historial', [HistorialController::class, 'index'])->name('historial.index');

    // MÓDULO DE REPORTES
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    // Aviso de deuda/corte por WhatsApp (incluye socios dados de baja que aún deben)
    Route::get('/reportes/socios/{socio}/whatsapp', [ReporteController::class, 'notificar'])->name('reportes.notificar')->withTrashed();

    // RUTA AJAX: Obtener la última lectura
    Route::get('/lecturas/ultima/{usuario_id}', [LecturaController::class, 'obtenerUltimaLectura'])->name('lecturas.ultima');

    // Cobro de lecturas
    Route::patch('/lecturas/{lectura}/pagar', [LecturaController::class, 'pagar'])->name('lecturas.pagar');

    // CRUD de Lecturas de agua
    Route::resource('lecturas', LecturaController::class)->except('show');

    // ARQUEO DE CAJA MENSUAL (QR / efectivo)
    Route::get('/finanzas/arqueo/exportar', [ArqueoController::class, 'exportar'])->name('arqueo.exportar');
    Route::get('/finanzas/arqueo', [ArqueoController::class, 'index'])->name('arqueo.index');

    // QR de pago de la OTB (imagen y fecha de vencimiento)
    Route::post('/finanzas/qr', [QrPagoController::class, 'update'])->name('finanzas.qr');

    // FINANZAS: registro y edición de balances (la consulta es para todos)
    Route::resource('finanzas', BalanceMensualController::class)->except(['index', 'show']);

    // MÓDULO DE MULTAS: gestión (la lista es para todos)
    Route::post('/multas/{multa}/pagar', [MultaController::class, 'pagar'])->name('multas.pagar');
    Route::resource('multas', MultaController::class)->except(['index', 'show']);

    // CRUD DE TARIFAS
    Route::resource('tarifas-multas', TarifaMultaController::class)->only(['index', 'store', 'update', 'destroy']);
});

// ==========================================================
// CUALQUIER USUARIO AUTENTICADO
// ==========================================================
Route::middleware('auth')->group(function () {

    // Vista del socio: solo sus propias lecturas
    Route::get('/mi-consumo', [LecturaController::class, 'miConsumo'])->name('socio.consumo');

    // Historial de pagos confirmados del socio (agua y multas)
    Route::get('/mis-pagos/exportar', [MisPagosController::class, 'exportar'])->name('socio.pagos.exportar');
    Route::get('/mis-pagos', [MisPagosController::class, 'index'])->name('socio.pagos');

    // Finanzas: consulta pública para los socios (transparencia de la OTB)
    Route::get('/finanzas/exportar', [BalanceMensualController::class, 'exportar'])->name('finanzas.exportar');
    Route::get('/finanzas', [BalanceMensualController::class, 'index'])->name('finanzas.index');
    Route::get('/finanzas/{finanza}/comprobante', [BalanceMensualController::class, 'comprobante'])->name('finanzas.comprobante');

    // Multas: el socio solo ve las suyas (filtrado en el controlador)
    Route::get('/multas/exportar', [MultaController::class, 'exportar'])->name('multas.exportar');
    Route::get('/multas', [MultaController::class, 'index'])->name('multas.index');

    // Perfil de Usuario
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
