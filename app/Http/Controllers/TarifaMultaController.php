<?php

namespace App\Http\Controllers;

use App\Http\Requests\TarifaMultaRequest;
use App\Models\TarifaMulta;

class TarifaMultaController extends Controller
{
    public function index()
    {
        $tarifas = TarifaMulta::all();

        return view('multas.tarifas', compact('tarifas'));
    }

    public function store(TarifaMultaRequest $request)
    {
        TarifaMulta::create($request->validated());

        return redirect()->route('tarifas-multas.index')
            ->with('success', 'Tarifa «'.$request->validated('nombre').'» creada ✓');
    }

    public function update(TarifaMultaRequest $request, TarifaMulta $tarifas_multa)
    {
        $tarifas_multa->update($request->validated());

        return redirect()->route('tarifas-multas.index')
            ->with('success', 'Tarifa «'.$tarifas_multa->nombre.'» actualizada ✓');
    }

    public function destroy(TarifaMulta $tarifas_multa)
    {
        $tarifas_multa->delete();

        return redirect()->route('tarifas-multas.index')
            ->with('success', 'Tarifa «'.$tarifas_multa->nombre.'» eliminada.');
    }
}
