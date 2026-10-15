<?php

namespace App\Http\Requests;

use App\Models\Lectura;
use App\Support\Meses;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LecturaRequest extends FormRequest
{
    // Lectura del mes anterior y del siguiente del mismo socio (si existen)
    private ?Lectura $previa = null;

    private ?Lectura $siguiente = null;

    // El acceso por rol lo controla el middleware de la ruta
    public function authorize(): bool
    {
        return true;
    }

    /**
     * La lectura anterior la pone el servidor: es la lectura actual del mes previo
     * del socio. Lo que mande el navegador solo vale para la primera lectura
     * (lectura inicial del medidor), así no se puede alterar el consumo.
     */
    protected function prepareForValidation(): void
    {
        $socio = filter_var($this->input('user_id'), FILTER_VALIDATE_INT);
        $mes = filter_var($this->input('mes'), FILTER_VALIDATE_INT);
        $gestion = filter_var($this->input('gestion'), FILTER_VALIDATE_INT);

        if (! $socio || ! $mes || ! $gestion) {
            return;
        }

        $ignorar = $this->route('lectura')?->id;
        $this->previa = Lectura::anteriorA($socio, $mes, $gestion, $ignorar);
        $this->siguiente = Lectura::siguienteA($socio, $mes, $gestion, $ignorar);

        if ($this->previa) {
            $this->merge(['lectura_anterior' => (float) $this->previa->lectura_actual]);
        }
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
            'lectura_actual' => ['required', 'numeric', 'gte:lectura_anterior', $this->noSuperaElMesSiguiente(...)],
        ];
    }

    /**
     * Si ya existe la lectura de un mes posterior (se está cargando un mes atrasado
     * o corrigiendo uno), el medidor no puede marcar más que en ese mes. Si ese mes
     * ya se cobró, su lectura anterior no puede cambiar.
     */
    private function noSuperaElMesSiguiente(string $atributo, mixed $valor, Closure $fallar): void
    {
        if (! $this->siguiente || ! is_numeric($valor)) {
            return;
        }

        $periodo = $this->siguiente->periodo;

        if ($this->siguiente->estado === Lectura::PAGADO) {
            if ((float) $valor !== (float) $this->siguiente->lectura_anterior) {
                $fallar("La lectura de {$periodo} ya se cobró partiendo de {$this->m3($this->siguiente->lectura_anterior)}: la lectura actual debe ser {$this->m3($this->siguiente->lectura_anterior)}.");
            }

            return;
        }

        if ((float) $valor > (float) $this->siguiente->lectura_actual) {
            $fallar("La lectura actual no puede ser mayor que la de {$periodo} ({$this->m3($this->siguiente->lectura_actual)}).");
        }
    }

    private function m3($valor): string
    {
        return rtrim(rtrim(number_format((float) $valor, 2, '.', ''), '0'), '.').' m³';
    }

    public function messages(): array
    {
        $periodo = Meses::nombre(Meses::numero($this->input('mes'))).' '.$this->input('gestion');

        return [
            'mes.unique' => "Este socio ya tiene una lectura registrada para {$periodo}. Edita esa lectura en lugar de crear otra.",
        ];
    }
}
