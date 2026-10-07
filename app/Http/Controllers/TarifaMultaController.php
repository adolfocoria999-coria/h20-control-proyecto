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
            ->with('success', '¡Tipo de multa creada correctamente!');
    }

    public function update(TarifaMultaRequest $request, TarifaMulta $tarifas_multa)
    {
        $tarifas_multa->update($request->validated());

        return redirect()->route('tarifas-multas.index')
            ->with('success', '¡Tarifa/Multa actualizada correctamente!');
    }

    public function destroy(TarifaMulta $tarifas_multa)
    {
        $tarifas_multa->delete();

        return redirect()->route('tarifas-multas.index')
            ->with('success', 'Tarifa eliminada con éxito.');
    }
}
