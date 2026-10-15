<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Alta y edición de usuarios (solo superadmin).
 */
class UsuarioRequest extends FormRequest
{
    // Nombre: letras (con tildes y ñ), espacios, punto, apóstrofo y guion
    public const PATRON_NOMBRE = "/^[\pL][\pL\s.'-]*$/u";

    // C.I. boliviano: 5 a 10 dígitos, complemento opcional (-1A) y extensión opcional (LP, CB, ...)
    public const PATRON_CI = '/^\d{5,10}(-[0-9A-Z]{2})?( (LP|CB|SC|OR|PT|CH|TJ|BE|PD))?$/';

    // Celular boliviano: 8 dígitos que empiezan con 6 o 7
    public const PATRON_TELEFONO = '/^[67]\d{7}$/';

    // El acceso por rol lo controla el middleware de la ruta
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Ordena lo escrito antes de validar: espacios de más, mayúsculas del C.I.,
     * guiones o el +591 del celular, y el correo en minúsculas.
     */
    protected function prepareForValidation(): void
    {
        $limpiar = fn ($valor) => is_string($valor) ? trim(preg_replace('/\s+/', ' ', $valor)) : $valor;

        $telefono = is_string($this->telefono) ? preg_replace('/[\s\-().]/', '', $this->telefono) : $this->telefono;
        if (is_string($telefono) && preg_match('/^\+?591(\d{8})$/', $telefono, $m)) {
            $telefono = $m[1];
        }

        $this->merge([
            'name' => $limpiar($this->name),
            'ci' => is_string($this->ci) ? Str::upper($limpiar($this->ci)) : $this->ci,
            'telefono' => $telefono,
            'email' => is_string($this->email) ? Str::lower(trim($this->email)) : $this->email,
        ]);
    }

    public function rules(): array
    {
        $usuario = $this->route('usuario');

        return [
            'name' => ['required', 'string', 'min:3', 'max:100', 'regex:'.self::PATRON_NOMBRE],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($usuario)],
            // Obligatoria al crear; al editar, vacía significa "no cambiar"
            'password' => [$usuario ? 'nullable' : 'required', 'string', 'min:8', 'max:72'],
            'rol_id' => 'required|integer|exists:rols,id',
            'ci' => ['nullable', 'string', 'max:20', 'regex:'.self::PATRON_CI, Rule::unique('users')->ignore($usuario)],
            'telefono' => ['nullable', 'string', 'regex:'.self::PATRON_TELEFONO],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'El nombre solo puede tener letras y espacios (sin números ni símbolos).',
            'ci.regex' => 'El carnet de identidad debe tener de 5 a 10 números, con complemento opcional (ej.: 1234567, 1234567-1A o 1234567 LP).',
            'ci.unique' => 'Ya hay un socio registrado con ese carnet de identidad.',
            'telefono.regex' => 'El teléfono debe ser un celular de 8 dígitos que empiece con 6 o 7 (ej.: 71234567).',
            'email.unique' => 'Ya hay un socio registrado con ese correo.',
        ];
    }
}
