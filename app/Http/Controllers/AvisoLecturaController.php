<?php

namespace App\Http\Controllers;

use App\Models\Lectura;
use App\Services\AvisoLectura;
use App\Services\Historial;
use App\Support\Meses;
use App\Support\WhatsApp;
use Illuminate\Http\Request;

/**
 * Aviso por WhatsApp "con un toque" de la lectura del mes: Hacienda ve a quién
 * falta avisar y cada toque abre WhatsApp con el mensaje ya escrito.
 */
class AvisoLecturaController extends Controller
{
    public function index(Request $request)
    {
        $mes = Meses::numero($request->get('mes')) ?? (int) date('n');
        $gestion = $request->integer('gestion') ?: (int) date('Y');

        $lecturas = Lectura::with('usuario:id,name,telefono,zona,orden_recorrido')
            ->where(['mes' => $mes, 'gestion' => $gestion])
            ->get()
            ->sortBy(fn (Lectura $l) => [$l->usuario?->zona ?? 'zzz', $l->usuario?->orden_recorrido ?? 9999, $l->usuario?->name])
            ->values();

        $pendientes = $lecturas->where('estado', Lectura::PENDIENTE);

        return view('lecturas.avisos', [
            'lecturas' => $lecturas,
            'mes' => $mes,
            'gestion' => $gestion,
            'resumen' => [
                'por_avisar' => $pendientes->whereNull('avisado_at')->filter(fn ($l) => WhatsApp::numero($l->usuario?->telefono))->count(),
                'avisados' => $lecturas->whereNotNull('avisado_at')->count(),
                'sin_celular' => $pendientes->filter(fn ($l) => ! WhatsApp::numero($l->usuario?->telefono))->count(),
                'pagadas' => $lecturas->where('estado', Lectura::PAGADO)->count(),
            ],
            'gestiones' => range((int) date('Y') + 1, (int) date('Y') - 4),
        ]);
    }

    /**
     * Marca el aviso como enviado, lo registra y abre WhatsApp con el mensaje.
     */
    public function avisar(Lectura $lectura)
    {
        $socio = $lectura->usuario;

        if ($lectura->estado === Lectura::PAGADO) {
            return back()->with('error', "La lectura de {$socio->name} ya está pagada: no hace falta avisar.");
        }

        $enlace = WhatsApp::enlace($socio->telefono, AvisoLectura::mensaje($lectura));
        if (! $enlace) {
            return back()->with('error', "{$socio->name} no tiene un número de celular válido registrado: avísale en persona.");
        }

        $reenvio = $lectura->avisado_at !== null;
        $lectura->forceFill(['avisado_at' => now()])->saveQuietly();

        Historial::registrar(
            'notificacion',
            'lecturas',
            ($reenvio ? 'Reenvió' : 'Envió').' por WhatsApp la lectura de '.$lectura->periodo." a {$socio->name} ("
                .WhatsApp::numero($socio->telefono).') por Bs. '.number_format($lectura->monto, 2, ',', '.'),
            $lectura,
        );

        return redirect()->away($enlace);
    }
}
