<?php

namespace App\Http\Controllers;

use App\Models\Aporte;
use App\Models\Multa;
use App\Models\User;
use App\Services\Comunicar;
use App\Services\Historial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Aportes (cuotas extraordinarias): Hacienda registra la actividad y el monto,
 * a cada socio se le anota su cuota pendiente y, si se elige, se envía una
 * notificación a todos. Las cuotas se cobran como las multas (QR o efectivo).
 */
class AporteController extends Controller
{
    public function create()
    {
        return view('aportes.create', ['totalSocios' => User::socios()->count()]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'titulo' => 'required|string|min:3|max:120',
            'descripcion' => 'nullable|string|max:1000',
            'monto' => 'required|numeric|min:0.5|max:100000',
            'fecha_limite' => 'nullable|date|after_or_equal:today',
            'notificar' => 'nullable|boolean',
        ], [
            'fecha_limite.after_or_equal' => 'La fecha límite no puede ser anterior a hoy.',
        ], ['titulo' => 'actividad', 'descripcion' => 'descripción', 'fecha_limite' => 'fecha límite']);

        $socios = User::socios()->pluck('id');
        if ($socios->isEmpty()) {
            return back()->withInput()->with('error', 'No hay socios registrados para cobrarles el aporte.');
        }

        $aporte = DB::transaction(function () use ($datos, $socios, $request) {
            $aporte = Aporte::create([
                'titulo' => trim($datos['titulo']),
                'descripcion' => isset($datos['descripcion']) ? trim($datos['descripcion']) : null,
                'monto' => round((float) $datos['monto'], 2),
                'fecha_limite' => $datos['fecha_limite'] ?? null,
                'user_id' => $request->user()->id,
            ]);

            // Una cuota pendiente por socio (en bloque: con 1000 socios son 1000 filas)
            $ahora = now();
            foreach ($socios->chunk(500) as $grupo) {
                Multa::insert($grupo->map(fn ($id) => [
                    'user_id' => $id,
                    'aporte_id' => $aporte->id,
                    'tipo_multa' => $aporte->titulo,
                    'monto' => $aporte->monto,
                    'motivo' => $aporte->descripcion ? mb_substr($aporte->descripcion, 0, 255) : null,
                    'fecha_multa' => $ahora->toDateString(),
                    'estado' => Multa::PENDIENTE,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ])->all());
            }

            return $aporte;
        });

        Historial::registrar('creado', 'multas', 'Registró el aporte «'.$aporte->titulo.'» de Bs. '
            .self::bs($aporte->monto).' para '.$socios->count().' socio(s)', $aporte);

        if ($request->boolean('notificar')) {
            $comunicado = Comunicar::aTodos($request->user(), 'Aporte: '.$aporte->titulo, self::mensaje($aporte));
            $aporte->update(['comunicado_id' => $comunicado->id]);
        }

        return redirect()->route('multas.index', ['aporte' => $aporte->id])->with('success',
            'Aporte registrado ✓ Se anotó una cuota de Bs. '.self::bs($aporte->monto).' a '.$socios->count().' socios'
            .($request->boolean('notificar') ? ' y se les envió una notificación.' : '.'));
    }

    public function destroy(Aporte $aporte)
    {
        if ($aporte->cuotas()->where('estado', Multa::PAGADO)->exists()) {
            return back()->with('error', 'No se puede eliminar el aporte «'.$aporte->titulo.'»: algunos socios ya lo pagaron. Si fue un error, condona las cuotas pendientes.');
        }

        DB::transaction(function () use ($aporte) {
            $aporte->comunicado?->delete();
            $aporte->delete(); // sus cuotas pendientes se borran con él
        });

        Historial::registrar('eliminado', 'multas', 'Eliminó el aporte «'.$aporte->titulo.'» y sus cuotas pendientes');

        return redirect()->route('multas.index')->with('success', 'Aporte eliminado junto con sus cuotas pendientes.');
    }

    /**
     * Texto de la notificación a los socios: qué es, cuánto, hasta cuándo y cómo pagar.
     */
    public static function mensaje(Aporte $aporte): string
    {
        $lineas = array_filter([
            $aporte->descripcion,
            'Monto por socio: Bs. '.self::bs($aporte->monto).'.',
            $aporte->fecha_limite ? 'Fecha límite: '.$aporte->fecha_limite->format('d/m/Y').'.' : null,
            'Puedes pagarlo con el QR de la OTB (en "Mi Consumo") o en efectivo a Hacienda.',
            'Si pagas por QR, escribe en la glosa "'.$aporte->titulo.' - tu nombre" y envía el comprobante por WhatsApp a Hacienda'
                .(config('otb.telefono_finanzas') ? ' ('.config('otb.telefono_finanzas').')' : '').'.',
            'Tu cuota aparece en "Multas y Aportes".',
        ]);

        return implode("\n", $lineas);
    }

    private static function bs(float|string $monto): string
    {
        return number_format((float) $monto, 2, ',', '.');
    }
}
