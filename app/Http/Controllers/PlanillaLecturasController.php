<?php

namespace App\Http\Controllers;

use App\Models\Ajuste;
use App\Models\Lectura;
use App\Models\User;
use App\Support\Meses;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Carga rápida del mes: todos los socios en una planilla para anotar la lectura
 * del medidor en fila. La lectura anterior la pone el sistema (la del mes previo
 * de cada socio); se escribe a mano solo la inicial de quien no tiene lecturas.
 */
class PlanillaLecturasController extends Controller
{
    // Avisa un consumo que supera esta cantidad de veces su promedio reciente
    public const VECES_PROMEDIO_ANORMAL = 3;

    // ...y que además es mayor a esta cantidad de m³ (evita alertas por consumos chicos)
    public const MINIMO_ANORMAL_M3 = 10;

    public function index(Request $request)
    {
        [$mes, $gestion] = $this->periodo($request);
        $filas = $this->filas($mes, $gestion);

        return view('lecturas.planilla', [
            'mes' => $mes,
            'gestion' => $gestion,
            'filas' => $filas,
            'registradas' => $filas->whereNotNull('registrada')->count(),
            'tarifa' => Ajuste::tarifaM3(),
            'gestiones' => range((int) date('Y') + 1, (int) date('Y') - 4),
            'zonas' => $filas->pluck('socio.zona')->filter()->unique()->values(),
            'esGestion' => $request->user()->esAdmin(),
        ]);
    }

    public function store(Request $request)
    {
        [$mes, $gestion] = $this->periodo($request);
        $entradas = (array) $request->input('lecturas', []);
        $periodo = Meses::nombre($mes)." {$gestion}";

        $nuevas = [];
        $errores = [];
        $yaRegistradas = []; // anotadas en el celular, pero alguien ya cargó ese mes
        $ids = [];           // socio => lectura, para que el celular suba las fotos

        foreach ($this->filas($mes, $gestion) as $fila) {
            $dato = $entradas[$fila->socio->id] ?? [];
            $actual = trim((string) ($dato['actual'] ?? ''));
            $observacion = trim(preg_replace('/\s+/', ' ', (string) ($dato['observacion'] ?? '')));

            // Fila vacía o mes ya registrado para ese socio: no se toca
            if ($actual === '') {
                if ($observacion !== '' && ! $fila->registrada) {
                    $errores["lecturas.{$fila->socio->id}.actual"] = "{$fila->socio->name}: anota también la lectura del medidor; la observación se guarda junto con ella.";
                }

                continue;
            }
            if ($fila->registrada) {
                $yaRegistradas[] = $fila->socio->name;
                $ids[$fila->socio->id] = $fila->registrada->id;

                continue;
            }

            $clave = "lecturas.{$fila->socio->id}";
            $nombre = $fila->socio->name;
            $anterior = $fila->anterior ?? trim((string) ($dato['anterior'] ?? ''));

            if (! is_numeric($actual) || (float) $actual < 0) {
                $errores["{$clave}.actual"] = "{$nombre}: la lectura actual debe ser un número.";

                continue;
            }
            if (! is_numeric($anterior) || (float) $anterior < 0) {
                $errores["{$clave}.anterior"] = "{$nombre}: es su primera lectura, escribe la lectura inicial del medidor.";

                continue;
            }
            if ((float) $actual < (float) $anterior) {
                $errores["{$clave}.actual"] = "{$nombre}: la lectura actual no puede ser menor que la anterior (".Lectura::m3($anterior).').';

                continue;
            }
            if (mb_strlen($observacion) > 255) {
                $errores["{$clave}.observacion"] = "{$nombre}: la observación no puede tener más de 255 caracteres.";

                continue;
            }
            if ($error = Lectura::errorConSiguiente($fila->siguiente, (float) $actual)) {
                $errores["{$clave}.actual"] = "{$nombre}: {$error}";

                continue;
            }

            $nuevas[] = [
                'user_id' => $fila->socio->id,
                'mes' => $mes,
                'gestion' => $gestion,
                'lectura_anterior' => (float) $anterior,
                'lectura_actual' => (float) $actual,
                'observacion' => $observacion !== '' ? $observacion : null,
            ];
        }

        // Todo o nada: si una fila tiene error no se guarda ninguna y se conserva lo escrito
        if ($errores) {
            return $request->expectsJson()
                ? response()->json(['errores' => $errores], 422)
                : back()->withErrors($errores)->withInput();
        }

        if (! $nuevas) {
            $mensaje = $yaRegistradas
                ? 'Ya estaban registradas: '.implode(', ', $yaRegistradas).'. No se cambió nada.'
                : 'No escribiste ninguna lectura nueva.';

            // Las enviadas desde el celular que ya estaban cargadas no son un error: se descartan
            return $request->expectsJson()
                ? response()->json(['guardadas' => 0, 'mensaje' => $mensaje, 'ids' => (object) $ids], $yaRegistradas ? 200 : 422)
                : back()->withInput()->with('error', $mensaje);
        }

        DB::transaction(function () use ($nuevas, &$ids) {
            foreach ($nuevas as $datos) {
                $lectura = Lectura::create($datos);
                $lectura->encadenarSiguiente();
                $ids[$lectura->user_id] = $lectura->id;
            }
        });

        $mensaje = count($nuevas).' lectura(s) de '.$periodo.' registrada(s) ✓'
            .($yaRegistradas ? ' Ya estaban registradas (no se cambiaron): '.implode(', ', $yaRegistradas).'.' : '');

        if ($request->expectsJson()) {
            session()->flash('success', $mensaje);

            return response()->json(['guardadas' => count($nuevas), 'mensaje' => $mensaje, 'ids' => (object) $ids]);
        }

        return redirect()->route('lecturas.planilla', ['mes' => $mes, 'gestion' => $gestion])->with('success', $mensaje);
    }

    /**
     * Una fila por socio: su lectura del mes (si ya existe), la anterior del medidor,
     * la del mes siguiente (si se carga un mes atrasado) y su consumo promedio reciente.
     *
     * @return Collection<int, object>
     */
    private function filas(int $mes, int $gestion): Collection
    {
        $orden = $gestion * 12 + $mes;
        // En el orden del recorrido del plomero: zona y n.º de orden (los que no tienen, al final)
        $socios = User::orderByRaw('zona is null, zona, orden_recorrido is null, orden_recorrido')
            ->orderBy('name')
            ->get(['id', 'name', 'ci', 'zona', 'orden_recorrido']);

        $columnas = ['id', 'user_id', 'mes', 'gestion', 'lectura_anterior', 'lectura_actual', 'consumo', 'estado', 'observacion', 'foto'];
        $delPeriodo = Lectura::where(['mes' => $mes, 'gestion' => $gestion])->get($columnas)->keyBy('user_id');
        $previas = Lectura::whereRaw('(gestion * 12 + mes) < ?', [$orden])
            ->orderByDesc('gestion')->orderByDesc('mes')->get($columnas)->groupBy('user_id');
        $siguientes = Lectura::whereRaw('(gestion * 12 + mes) > ?', [$orden])
            ->orderBy('gestion')->orderBy('mes')->get($columnas)->groupBy('user_id');

        return $socios->map(function (User $socio) use ($delPeriodo, $previas, $siguientes) {
            $historial = $previas->get($socio->id, collect());
            $previa = $historial->first();

            return (object) [
                'socio' => $socio,
                'registrada' => $delPeriodo->get($socio->id),
                'anterior' => $previa ? (float) $previa->lectura_actual : null,
                'periodo_anterior' => $previa?->periodo,
                'siguiente' => $siguientes->get($socio->id)?->first(),
                'promedio' => $historial->take(3)->avg('consumo'),
            ];
        });
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function periodo(Request $request): array
    {
        $gestion = $request->integer('gestion') ?: (int) date('Y');

        return [
            Meses::numero($request->input('mes')) ?? (int) date('n'),
            max(2000, min(2100, $gestion)),
        ];
    }
}
