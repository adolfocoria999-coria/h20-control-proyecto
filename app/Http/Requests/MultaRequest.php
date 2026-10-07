<?php

namespace App\Http\Requests;

use App\Enums\MetodoPago;
use App\Models\Multa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crear (individual o masivo) y editar multas.
 */
class MultaRequest extends FormRequest
{
    // El acceso por rol lo controla el middleware de la ruta
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $reglas = [
            'tarifa_multa_id' => ['required', Rule::when($this->input('tarifa_multa_id') !== 'otro', 'exists:tarifas_multas,id')],
            'monto' => 'required|numeric|min:0',
            'fecha_multa' => 'required|date',
            'motivo' => 'nullable|string|max:255',
            'tipo_multa_texto' => 'nullable|string|max:255',
        ];

        if ($this->isMethod('post')) {
            if ($this->esMasivo()) {
                $reglas['socios'] = 'required|array|min:1';
                $reglas['socios.*'] = 'exists:users,id';
            } else {
                $reglas['user_id'] = 'required|exists:users,id';
            }

            return $reglas;
        }

        return $reglas + [
            'user_id' => 'required|exists:users,id',
            'estado' => ['required', Rule::in([Multa::PENDIENTE, Multa::PAGADO, Multa::CONDONADO])],
            // Al marcarla como pagada hay que indicar cómo se cobró (para el arqueo)
            'metodo_pago' => ['nullable', 'required_if:estado,'.Multa::PAGADO, Rule::enum(MetodoPago::class)],
        ];
    }

    public function esMasivo(): bool
    {
        return $this->input('tipo_registro', $this->has('socios') ? 'masivo' : 'individual') === 'masivo';
    }
}
