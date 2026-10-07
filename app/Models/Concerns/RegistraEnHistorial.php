<?php

namespace App\Models\Concerns;

use App\Models\Actividad;
use App\Services\Historial;

/**
 * Registra en el historial cada alta, cambio y baja del modelo.
 *
 * El modelo debe definir:
 *  - moduloHistorial(): string           módulo (lecturas, multas, ...)
 *  - etiquetaHistorial(): string          "la lectura de Marzo 2026 de Ana"
 * y puede definir:
 *  - $ignorarEnHistorial                  campos que no cuentan como cambio
 *  - accionHistorial($evento, $cambios)   acción más precisa (pago, baja, ...)
 *  - descripcionHistorial($accion)        texto distinto al genérico
 */
trait RegistraEnHistorial
{
    public static function bootRegistraEnHistorial(): void
    {
        static::created(fn ($modelo) => $modelo->registrarEnHistorial('creado'));
        static::updated(fn ($modelo) => $modelo->registrarEnHistorial('actualizado'));
        static::deleted(fn ($modelo) => $modelo->registrarEnHistorial('eliminado'));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn ($modelo) => $modelo->registrarEnHistorial('restaurado'));
        }
    }

    protected function registrarEnHistorial(string $evento): void
    {
        $cambios = $evento === 'actualizado' ? $this->cambiosParaHistorial() : $this->datosParaHistorial($evento);

        // Cambios solo en campos calculados o internos (p. ej. remember_token): no es una acción del usuario
        if ($evento === 'actualizado' && $cambios === []) {
            return;
        }

        $accion = $this->accionHistorial($evento, $cambios);

        Historial::registrar($accion, $this->moduloHistorial(), $this->descripcionHistorial($accion), $this, $cambios);
    }

    abstract protected function moduloHistorial(): string;

    abstract protected function etiquetaHistorial(): string;

    protected function accionHistorial(string $evento, array $cambios): string
    {
        return $evento;
    }

    protected function descripcionHistorial(string $accion): string
    {
        return (Actividad::ACCIONES[$accion] ?? ucfirst($accion)).' '.$this->etiquetaHistorial();
    }

    /**
     * @return array<string, array{antes: mixed, despues: mixed}>
     */
    protected function cambiosParaHistorial(): array
    {
        $cambios = [];

        foreach ($this->getChanges() as $campo => $nuevo) {
            if (in_array($campo, $this->camposIgnoradosEnHistorial(), true)) {
                continue;
            }
            $cambios[$campo] = [
                'antes' => $this->valorParaHistorial($campo, $this->getRawOriginal($campo)),
                'despues' => $this->valorParaHistorial($campo, $nuevo),
            ];
        }

        return $cambios;
    }

    /**
     * Foto de los datos al crear o eliminar.
     *
     * @return array<string, array{antes: mixed, despues: mixed}>
     */
    protected function datosParaHistorial(string $evento): array
    {
        $datos = [];

        foreach ($this->getAttributes() as $campo => $valor) {
            if ($campo === $this->getKeyName() || in_array($campo, $this->camposIgnoradosEnHistorial(), true)) {
                continue;
            }
            $valor = $this->valorParaHistorial($campo, $valor);
            $datos[$campo] = $evento === 'creado'
                ? ['antes' => null, 'despues' => $valor]
                : ['antes' => $valor, 'despues' => null];
        }

        return $datos;
    }

    private function valorParaHistorial(string $campo, mixed $valor): mixed
    {
        // Nunca guardar contraseñas, ni siquiera cifradas
        if ($campo === 'password') {
            return $valor === null ? null : '••••••';
        }

        return $valor instanceof \DateTimeInterface ? $valor->format('Y-m-d H:i:s') : $valor;
    }

    private function camposIgnoradosEnHistorial(): array
    {
        return array_merge(
            ['created_at', 'updated_at', 'deleted_at', 'remember_token'],
            property_exists($this, 'ignorarEnHistorial') ? $this->ignorarEnHistorial : [],
        );
    }
}
