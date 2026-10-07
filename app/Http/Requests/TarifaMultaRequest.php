<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TarifaMultaRequest extends FormRequest
{
    // El acceso por rol lo controla el middleware de la ruta
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'monto_predeterminado' => 'required|numeric|min:0',
            'descripcion' => 'nullable|string',
        ];
    }
}
