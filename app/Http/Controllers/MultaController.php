<?php

namespace App\Http\Controllers;

use App\Enums\MetodoPago;
use App\Http\Requests\MultaRequest;
use App\Models\Multa;
use App\Models\TarifaMulta;
use App\Models\User;
use App\Services\CsvExporter;
use App\Services\Historial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MultaController extends Controller
{
    public function index(Request $request)
    {
        $esGestion = $request->user()->esAdmin();

        $multas = $this->consultaFiltrada($request)
            ->with('socio')
            ->orderBy('fecha_multa', 'desc')
            ->paginate(15);

        $tarifas = TarifaMulta::all();

        // Resumen: el socio solo ve sus propios totales
        $resumen = $this->consultaVisible($request);
        $totalPendiente = (clone $resumen)->where('estado', Multa::PENDIENTE)->sum('monto');
        $totalCobrado = (clone $resumen)->where('estado', Multa::PAGADO)->sum('monto');
        $cantPendientes = (clone $resumen)->where('estado', Multa::PENDIENTE)->count();

        return view('multas.index', compact('multas', 'tarifas', 'totalPendiente', 'totalCobrado', 'cantPendientes', 'esGestion'));
    }

    public function create()
    {
        $socios = User::socios()->orderBy('name')->get();
        $tarifas = TarifaMulta::all();

        return view('multas.create', compact('socios', 'tarifas'));
    }

    public function store(MultaRequest $request)
    {
        $sociosIds = $request->esMasivo() ? $request->socios : [$request->user_id];

        foreach ($sociosIds as $socioId) {
            Multa::create($this->datosMulta($request) + [
                'user_id' => $socioId,
                'estado' => Multa::PENDIENTE,
            ]);
        }

        return redirect()->route('multas.index')->with('success', 'Multa(s) registrada(s) con éxito. ✅');
    }

    public function edit(Multa $multa)
    {
        $socios = User::socios()->orderBy('name')->get();
        $tarifas = TarifaMulta::all();

        return view('multas.edit', compact('multa', 'socios', 'tarifas'));
    }

    public function update(MultaRequest $request, Multa $multa)
    {
        $multa->update($this->datosMulta($request) + [
            'user_id' => $request->user_id,
            'estado' => $request->estado,
            'metodo_pago' => $request->estado === Multa::PAGADO ? $request->metodo_pago : null,
        ]);

        return redirect()->route('multas.index')->with('success', 'Multa actualizada correctamente. ✏️');
    }

    public function destroy(Multa $multa)
    {
        $multa->delete();

        return redirect()->route('multas.index')->with('success', 'La multa ha sido eliminada. 🗑️');
    }

    public function pagar(Request $request, Multa $multa)
    {
        if ($multa->estado === Multa::PAGADO) {
            return back()->with('error', 'La multa ya se encuentra pagada.');
        }

        $datos = $request->validate([
            'metodo_pago' => ['required', Rule::enum(MetodoPago::class)],
            'comprobante' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            'metodo_pago.required' => 'Elige si el pago fue por QR o en efectivo.',
        ]);

        $multa->update([
            'estado' => Multa::PAGADO,
            'metodo_pago' => $datos['metodo_pago'],
            'comprobante_pago' => $request->file('comprobante')?->store('comprobantes_multas', 'local'),
        ]);

        return back()->with('success', 'Pago de multa registrado ('.MetodoPago::from($datos['metodo_pago'])->etiqueta().'). ✅');
    }

    public function exportar(Request $request)
    {
        $filas = $this->consultaFiltrada($request)
            ->with(['socio', 'tarifa'])
            ->orderBy('fecha_multa', 'desc')
            ->lazy()
            ->map(fn (Multa $m) => [
                $m->id,
                $m->socio->name ?? 'Usuario Desconocido',
                $m->socio->email ?? 'Sin Email',
                $m->tipo_multa && $m->tipo_multa !== 'Multa'
                    ? $m->tipo_multa
                    : ($m->tarifa->nombre ?? ($m->motivo ?? 'Multa')),
                CsvExporter::dinero($m->monto),
                $m->fecha_multa->format('d/m/Y'),
                ucfirst($m->estado),
            ]);

        Historial::registrar('exportacion', 'multas', $request->user()->esAdmin() ? 'Exportó las multas a Excel' : 'Exportó sus multas a Excel');

        return CsvExporter::descargar(
            'multas_otb_'.date('Y-m-d_H-i').'.csv',
            ['ID', 'Socio', 'Email', 'Tipo Infracción / Motivo', 'Monto (Bs.)', 'Fecha Multa', 'Estado'],
            $filas
        );
    }

    /**
     * Multas que el usuario puede ver: todas para admin, solo las suyas para el socio.
     */
    private function consultaVisible(Request $request): Builder
    {
        $usuario = $request->user();

        return Multa::query()->when(! $usuario->esAdmin(), fn ($q) => $q->where('user_id', $usuario->id));
    }

    /**
     * Multas visibles con los filtros de estado y búsqueda (la búsqueda es solo para admin).
     */
    private function consultaFiltrada(Request $request): Builder
    {
        return $this->consultaVisible($request)
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('buscar') && $request->user()->esAdmin(), function ($q) use ($request) {
                $q->whereHas('socio', function ($s) use ($request) {
                    $s->where('name', 'like', '%'.$request->buscar.'%')
                        ->orWhere('email', 'like', '%'.$request->buscar.'%');
                });
            });
    }

    /**
     * Campos comunes al crear y editar. La opción "otro" no usa tarifa predefinida.
     */
    private function datosMulta(MultaRequest $request): array
    {
        $tarifa = $request->tarifa_multa_id === 'otro' ? null : TarifaMulta::find($request->tarifa_multa_id);

        // El nombre sale de la tarifa en la base; en "otro" se conserva el texto previo o se usa el motivo
        $tipoMulta = $tarifa?->nombre
            ?? $request->input('tipo_multa_texto')
            ?? $request->input('motivo')
            ?? 'Otra Infracción';

        return [
            'tarifa_multa_id' => $tarifa?->id,
            'tipo_multa' => $tipoMulta,
            'monto' => $request->monto,
            'motivo' => $request->motivo,
            'fecha_multa' => $request->fecha_multa,
        ];
    }
}
