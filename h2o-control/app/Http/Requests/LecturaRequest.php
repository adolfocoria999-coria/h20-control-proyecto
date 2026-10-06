<?php

namespace App\Http\Requests;

use App\Support\Meses;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LecturaRequest extends FormRequest
{
    // El acceso por rol lo controla el middleware de la ruta
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'mes' => [
                'required', 'integer', 'between:1,12',
                // Una sola lectura por socio y periodo (también lo exige la base de datos)
                Rule::unique('lecturas')
                    ->where('user_id', $this->input('user_id'))
                    ->where('gestion', $this->input('gestion'))
                    ->ignore($this->route('lectura')),
            ],
            'gestion' => 'required|integer|min:2000|max:2100',
            'lectura_anterior' => 'required|numeric|min:0',
            'lectura_actual' => 'required|numeric|gte:lectura_anterior',
        ];
    }

    public function messages(): array
    {
        $periodo = Meses::nombre(Meses::numero($this->input('mes'))).' '.$this->input('gestion');

        return [
            'mes.unique' => "Este socio ya tiene una lectura registrada para {$periodo}. Edita esa lectura en lugar de crear otra.",
        ];
    }
}
