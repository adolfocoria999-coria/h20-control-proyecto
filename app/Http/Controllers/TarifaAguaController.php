<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Ajuste;
use App\Models\Lectura;
use App\Services\Historial;
use Illuminate\Http\Request;

/**
 * Tarifa del agua por m³ (solo superadmin). La nueva tarifa se aplica a las
 * lecturas que se registren desde ese momento; las ya registradas conservan la suya.
 */
class TarifaAguaController extends Controller
{
    public function index()
    {
        $tarifa = Ajuste::tarifaM3();

        return view('tarifa-agua.index', [
            'tarifa' => $tarifa,
            'cambios' => Actividad::where('modulo', 'tarifa_agua')->latest('id')->limit(20)->get(),
            // Lecturas sin cobrar que tienen otra tarifa (conservan la de su registro)
            'pendientesConOtraTarifa' => Lectura::pendientes()->where('tarifa_m3', '!=', $tarifa)->count(),
        ]);
    }

    public function update(Request $request)
    {
        $datos = $request->validate([
            'tarifa_m3' => 'required|numeric|min:0.1|max:1000',
        ], [], ['tarifa_m3' => 'tarifa por m³']);

        $anterior = Ajuste::tarifaM3();
        $nueva = round((float) $datos['tarifa_m3'], 2);

        if ($nueva === $anterior) {
            return back()->with('error', 'La tarifa ya es de Bs. '.number_format($nueva, 2, ',', '.').' por m³.');
        }

        Ajuste::guardar(Ajuste::TARIFA_M3, number_format($nueva, 2, '.', ''));

        Historial::registrar(
            'actualizado',
            'tarifa_agua',
            'Cambió la tarifa del agua de Bs. '.number_format($anterior, 2, ',', '.').' a Bs. '.number_format($nueva, 2, ',', '.').' por m³',
            cambios: ['tarifa_m3' => ['antes' => $anterior, 'despues' => $nueva]],
        );

        return redirect()->route('tarifa-agua.index')
            ->with('success', 'Tarifa actualizada ✓ Se aplica a las lecturas que se registren desde ahora.');
    }
}
