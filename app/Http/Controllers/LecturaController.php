<?php

namespace App\Http\Controllers;

use App\Enums\MetodoPago;
use App\Http\Requests\LecturaRequest;
use App\Models\Ajuste;
use App\Models\Lectura;
use App\Models\User;
use App\Services\CsvExporter;
use App\Services\Historial;
use App\Services\QrPago;
use App\Support\Meses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LecturaController extends Controller
{
    public const POR_PAGINA = 20;

    // 1. Lista de lecturas filtrada y paginada (la más reciente primero)
    public function index(Request $request)
    {
        $mes = Meses::numero($request->get('mes'));
        $gestion = $request->integer('gestion') ?: null;

        $lecturas = $this->consultaFiltrada($request)
            ->with('usuario')
            ->orderByDesc('gestion')->orderByDesc('mes')->orderByDesc('id')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        // Tarjetas de resumen: totales generales calculados en SQL, sin cargar todas las filas
        $tarifa = Ajuste::tarifaM3();
        $totalPendiente = round((float) Lectura::pendientes()->sum('consumo') * $tarifa, 2);
        $totalCobrado = round((float) Lectura::where('estado', Lectura::PAGADO)->sum('consumo') * $tarifa, 2);
        $cantPendientes = Lectura::pendientes()->count();

        $gestiones = Lectura::query()->distinct()->orderByDesc('gestion')->pluck('gestion');

        return view('lecturas.index', compact('lecturas', 'totalPendiente', 'totalCobrado', 'cantPendientes', 'gestiones', 'mes', 'gestion'));
    }

    /**
     * Filtros de la lista: socio (nombre o C.I.), mes, gestión y estado.
     */
    private function consultaFiltrada(Request $request): Builder
    {
        $mes = Meses::numero($request->get('mes'));
        $gestion = $request->integer('gestion') ?: null;
        $buscar = trim((string) $request->get('socio'));

        return Lectura::query()
            ->when($buscar !== '', fn ($q) => $q->whereHas('usuario', fn ($u) => $u->withTrashed()
                ->where(fn ($w) => $w->where('name', 'like', "%{$buscar}%")->orWhere('ci', 'like', "%{$buscar}%"))))
            ->when($mes, fn ($q) => $q->where('mes', $mes))
            ->when($gestion, fn ($q) => $q->where('gestion', $gestion))
            ->when(in_array($request->get('estado'), [Lectura::PENDIENTE, Lectura::PAGADO], true),
                fn ($q) => $q->where('estado', $request->get('estado')));
    }

    // 2. Formulario para registrar una lectura
    public function create()
    {
        $socios = User::all();

        return view('lecturas.create', compact('socios'));
    }

    // 2.1 API/AJAX: Obtiene la última lectura registrada de un socio
    public function obtenerUltimaLectura(Request $request, $usuario_id)
    {
        $ignoreId = $request->query('ignore_id');

        $ultimaLectura = Lectura::where('user_id', $usuario_id)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->orderBy('id', 'desc')
            ->first();

        return response()->json([
            'tiene_lectura' => (bool) $ultimaLectura,
            'lectura_actual' => $ultimaLectura ? $ultimaLectura->lectura_actual : 0,
        ]);
    }

    // 3. Guarda la nueva lectura (el consumo lo calcula el modelo)
    public function store(LecturaRequest $request)
    {
        Lectura::create($request->validated());

        return redirect()->route('lecturas.index')->with('success', 'Lectura registrada ✓');
    }

    // 4. Formulario para editar
    public function edit(Lectura $lectura)
    {
        $socios = User::all();

        return view('lecturas.edit', compact('lectura', 'socios'));
    }

    // 5. Actualiza el registro
    public function update(LecturaRequest $request, Lectura $lectura)
    {
        $lectura->update($request->validated());

        return redirect()->route('lecturas.index')->with('success', 'Lectura actualizada ✓');
    }

    // 6. Eliminar registro
    public function destroy(Lectura $lectura)
    {
        $lectura->delete();

        return redirect()->route('lecturas.index')->with('success', 'La lectura fue eliminada ✓');
    }

    // Vista exclusiva de lecturas del socio autenticado
    public function miConsumo()
    {
        $misLecturas = Lectura::where('user_id', auth()->id())
            ->orderBy('id', 'desc')
            ->get();

        $qr = QrPago::actual();

        return view('socio.consumo', compact('misLecturas', 'qr'));
    }

    // Cambiar estado a pagado
    // Registra el cobro indicando cómo pagó el socio (QR o efectivo)
    public function pagar(Request $request, Lectura $lectura)
    {
        if ($lectura->estado === Lectura::PAGADO) {
            return back()->with('error', 'Esta lectura ya está pagada.');
        }

        $datos = $request->validate([
            'metodo_pago' => ['required', Rule::enum(MetodoPago::class)],
        ], [
            'metodo_pago.required' => 'Elige si el pago fue por QR o en efectivo.',
        ]);

        $lectura->update(['estado' => Lectura::PAGADO, 'metodo_pago' => $datos['metodo_pago']]);

        return back()->with('success', '¡Pago confirmado ('.MetodoPago::from($datos['metodo_pago'])->etiqueta().')! La deuda ha sido saldada con éxito.');
    }

    // 8. Exportar lecturas a Excel (CSV)
    // Exporta lo mismo que muestra la lista (respeta los filtros)
    public function exportar(Request $request)
    {
        $filas = $this->consultaFiltrada($request)->with('usuario')->lazyByIdDesc(500)->map(fn (Lectura $lectura) => [
            $lectura->id,
            $lectura->usuario->name ?? 'Desconocido',
            $lectura->mes_nombre,
            $lectura->gestion,
            $lectura->lectura_anterior,
            $lectura->lectura_actual,
            $lectura->consumo,
            CsvExporter::dinero($lectura->monto),
            ucfirst($lectura->estado ?? Lectura::PENDIENTE),
        ]);

        Historial::registrar('exportacion', 'lecturas', 'Exportó las lecturas de agua a Excel');

        return CsvExporter::descargar(
            'lecturas_agua_'.date('Y-m-d_H-i').'.csv',
            ['ID', 'Socio', 'Mes', 'Gestión', 'Lectura Anterior (m³)', 'Lectura Actual (m³)', 'Consumo (m³)', 'Monto (Bs.)', 'Estado'],
            $filas
        );
    }
}
