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

        foreach ($this->filas($mes, $gestion) as $fila) {
            $dato = $entradas[$fila->socio->id] ?? [];
            $actual = trim((string) ($dato['actual'] ?? ''));

            // Fila vacía o mes ya registrado para ese socio: no se toca
            if ($actual === '' || $fila->registrada) {
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
            ];
        }

        // Todo o nada: si una fila tiene error no se guarda ninguna y se conserva lo escrito
        if ($errores) {
            return back()->withErrors($errores)->withInput();
        }

        if (! $nuevas) {
            return back()->withInput()->with('error', 'No escribiste ninguna lectura nueva.');
        }

        DB::transaction(function () use ($nuevas) {
            foreach ($nuevas as $datos) {
                Lectura::create($datos)->encadenarSiguiente();
            }
        });

        return redirect()->route('lecturas.planilla', ['mes' => $mes, 'gestion' => $gestion])
            ->with('success', count($nuevas).' lectura(s) de '.$periodo.' registrada(s) ✓');
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
        // En el orden del recorrido del técnico: zona y n.º de orden (los que no tienen, al final)
        $socios = User::orderByRaw('zona is null, zona, orden_recorrido is null, orden_recorrido')
            ->orderBy('name')
            ->get(['id', 'name', 'ci', 'zona', 'orden_recorrido']);

        $columnas = ['id', 'user_id', 'mes', 'gestion', 'lectura_anterior', 'lectura_actual', 'consumo', 'estado'];
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
