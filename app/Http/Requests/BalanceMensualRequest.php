<?php

namespace App\Http\Requests;

use App\Support\Meses;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BalanceMensualRequest extends FormRequest
{
    // El acceso por rol lo controla el middleware de la ruta
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mes' => [
                'required', 'integer', 'between:1,12',
                // Un solo balance por periodo (también lo exige la base de datos)
                Rule::unique('balance_mensuals')
                    ->where('gestion', $this->input('gestion'))
                    ->ignore($this->route('finanza')),
            ],
            'gestion' => 'required|integer|min:2000|max:2100',
            'ingresos' => 'required|numeric|min:0',
            'egresos' => 'required|numeric|min:0',
            'detalle' => 'nullable|string',
            'comprobante' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', // Máximo 5MB
        ];
    }

    public function messages(): array
    {
        $periodo = Meses::nombre(Meses::numero($this->input('mes'))).' '.$this->input('gestion');

        return [
            'mes.unique' => "Ya existe un balance para {$periodo}. Edítalo en lugar de crear otro.",
        ];
    }
}
