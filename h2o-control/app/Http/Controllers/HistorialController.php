<?php

namespace App\Http\Controllers;

use App\Enums\RolUsuario;
use App\Models\Actividad;
use App\Services\CsvExporter;
use App\Services\Historial;
use App\Support\Meses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HistorialController extends Controller
{
    /**
     * Historial con dos apartados (administración / usuarios), filtrado por año y mes.
     * Por defecto muestra el mes actual: así cada consulta lee solo una porción de la tabla.
     */
    public function index(Request $request)
    {
        $filtros = $this->filtros($request);

        $actividades = $this->consulta($filtros)
            ->with('usuario:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(config('historial.por_pagina'))
            ->withQueryString();

        // Totales de cada apartado en el mismo periodo (para las pestañas)
        $totalesPorSeccion = Actividad::query()
            ->periodo($filtros['gestion'], $filtros['mes'])
            ->selectRaw('seccion, count(*) as total')
            ->groupBy('seccion')
            ->pluck('total', 'seccion');

        $primerAnio = (int) (Actividad::min('created_at') ? substr((string) Actividad::min('created_at'), 0, 4) : date('Y'));
        $gestiones = range((int) date('Y'), min($primerAnio, (int) date('Y')));

        return view('historial.index', compact('actividades', 'filtros', 'totalesPorSeccion', 'gestiones'));
    }

    public function exportar(Request $request)
    {
        $filtros = $this->filtros($request);

        // Lo exportado es lo que existía al pedirlo (no incluye el registro de esta misma exportación)
        $ultimoId = (int) Actividad::max('id');

        // Recorre por id descendente (= más reciente primero) en bloques de 500: no carga todo en memoria
        $filas = $this->consulta($filtros)
            ->where('id', '<=', $ultimoId)
            ->lazyByIdDesc(500)
            ->map(fn (Actividad $a) => [
                $a->created_at->format('d/m/Y H:i:s'),
                $a->usuario_nombre,
                RolUsuario::etiqueta($a->rol),
                $a->accionEtiqueta(),
                $a->moduloEtiqueta(),
                $a->descripcion,
                $a->ip,
            ]);

        Historial::registrar('exportacion', 'historial', 'Exportó el historial de '.$this->nombreSeccion($filtros['seccion']).' de '.$this->nombrePeriodo($filtros).' a Excel');

        return CsvExporter::descargar(
            "historial_{$filtros['seccion']}_{$filtros['gestion']}".($filtros['mes'] ? '_'.str_pad($filtros['mes'], 2, '0', STR_PAD_LEFT) : '').'.csv',
            ['Fecha y hora', 'Usuario', 'Rol', 'Acción', 'Módulo', 'Descripción', 'IP'],
            $filas
        );
    }

    // ---------- Solo superadministrador ----------

    public function destroy(Actividad $actividad)
    {
        $actividad->delete();

        return back()->with('success', 'Registro eliminado del historial.');
    }

    /**
     * Borra todos los registros de un apartado en un año o mes (depuración de registros antiguos).
     */
    public function destroyPeriodo(Request $request)
    {
        $datos = $request->validate([
            'seccion' => ['required', Rule::in([Actividad::SECCION_ADMIN, Actividad::SECCION_USUARIO])],
            'gestion' => 'required|integer|min:2000|max:2100',
            'mes' => 'nullable|integer|between:1,12',
        ]);
        $filtros = ['seccion' => $datos['seccion'], 'gestion' => (int) $datos['gestion'], 'mes' => isset($datos['mes']) ? (int) $datos['mes'] : null];

        $eliminados = Actividad::seccion($filtros['seccion'])->periodo($filtros['gestion'], $filtros['mes'])->delete();

        return redirect()->route('historial.index', array_filter($filtros))
            ->with('success', "Se eliminaron {$eliminados} registro(s) del historial.");
    }

    // ---------- Apoyo ----------

    /**
     * @return array{seccion: string, gestion: int, mes: ?int, modulo: ?string, accion: ?string, buscar: string}
     */
    private function filtros(Request $request): array
    {
        $seccion = $request->get('seccion') === Actividad::SECCION_USUARIO ? Actividad::SECCION_USUARIO : Actividad::SECCION_ADMIN;
        $modulo = $request->get('modulo');
        $accion = $request->get('accion');

        return [
            'seccion' => $seccion,
            'gestion' => $request->integer('gestion') ?: (int) date('Y'),
            // Sin parámetro: mes actual. "todos" = todo el año.
            'mes' => $request->has('mes') ? Meses::numero($request->get('mes')) : (int) date('n'),
            'modulo' => array_key_exists((string) $modulo, Actividad::MODULOS) ? $modulo : null,
            'accion' => array_key_exists((string) $accion, Actividad::ACCIONES) ? $accion : null,
            'buscar' => trim((string) $request->get('buscar')),
        ];
    }

    private function consulta(array $filtros): Builder
    {
        return Actividad::query()
            ->seccion($filtros['seccion'])
            ->periodo($filtros['gestion'], $filtros['mes'])
            ->when($filtros['modulo'], fn ($q, $v) => $q->where('modulo', $v))
            ->when($filtros['accion'], fn ($q, $v) => $q->where('accion', $v))
            ->when($filtros['buscar'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('usuario_nombre', 'like', "%{$filtros['buscar']}%")
                ->orWhere('descripcion', 'like', "%{$filtros['buscar']}%")));
    }

    private function nombreSeccion(string $seccion): string
    {
        return $seccion === Actividad::SECCION_ADMIN ? 'administración' : 'usuarios';
    }

    private function nombrePeriodo(array $filtros): string
    {
        return $filtros['mes'] ? Meses::nombre($filtros['mes']).' '.$filtros['gestion'] : 'todo el año '.$filtros['gestion'];
    }
}
