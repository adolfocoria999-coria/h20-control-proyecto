<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de usuarios (solo superadmin).
 */
class UsuarioRequest extends FormRequest
{
    // El acceso por rol lo controla el middleware de la ruta
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $usuario = $this->route('usuario');

        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($usuario)],
            // Obligatoria al crear; al editar, vacía significa "no cambiar"
            'password' => [$usuario ? 'nullable' : 'required', 'string', 'min:6'],
            'rol_id' => 'required|integer|exists:rols,id',
            'ci' => 'nullable|string|max:20',
            'telefono' => 'nullable|string|max:20',
        ];
    }
}
