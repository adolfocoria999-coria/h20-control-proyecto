<?php

namespace App\Http\Controllers;

use App\Models\Lectura;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Foto del medidor de una lectura. La sube el plomero desde la planilla (el
 * celular ya la manda comprimida) o Hacienda desde el formulario de la lectura.
 */
class FotoMedidorController extends Controller
{
    public function store(Request $request, Lectura $lectura)
    {
        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096',
                // Revisa el contenido, no solo el nombre o el tipo declarado
                function (string $campo, $archivo, $fallar) {
                    if (! $archivo instanceof UploadedFile || ! @getimagesize($archivo->getRealPath())) {
                        $fallar('El archivo debe ser una foto.');
                    }
                },
            ],
        ], [
            'foto.image' => 'El archivo debe ser una foto.',
            'foto.max' => 'La foto no puede pesar más de 4 MB.',
        ]);

        if (! $request->user()->esAdmin()) {
            // La foto del plomero es el respaldo de una observación (fuga, medidor dañado...)
            if (blank($lectura->observacion)) {
                return $this->responder($request, 'La foto acompaña una observación: escribe primero qué observaste.', 422);
            }
            // Agrega la foto que falta; reemplazarla es tarea de Hacienda
            if ($lectura->foto) {
                return $this->responder($request, 'Esta lectura ya tiene foto.', 409);
            }
        }

        $anterior = $lectura->foto;
        $ruta = $request->file('foto')->storeAs(
            "medidores/{$lectura->gestion}-".str_pad((string) $lectura->mes, 2, '0', STR_PAD_LEFT),
            "lectura_{$lectura->id}_".now()->format('YmdHis').'.'.$request->file('foto')->extension(),
            'archivos'
        );

        $lectura->forceFill(['foto' => $ruta])->saveQuietly();

        if ($anterior && $anterior !== $ruta) {
            Storage::disk('archivos')->delete($anterior);
        }

        return $this->responder($request, 'Foto del medidor guardada ✓');
    }

    /**
     * Hacienda y el superadmin ven cualquier foto; el socio, la de su medidor.
     */
    public function show(Request $request, Lectura $lectura)
    {
        abort_if(! $request->user()->esAdmin() && $request->user()->id !== $lectura->user_id, 403, 'Esta foto no es de tu medidor.');
        abort_unless($lectura->foto && Storage::disk('archivos')->exists($lectura->foto), 404, 'Esta lectura no tiene foto.');

        return Storage::disk('archivos')->response($lectura->foto, "medidor_{$lectura->mes}_{$lectura->gestion}.jpg");
    }

    private function responder(Request $request, string $mensaje, int $estado = 200)
    {
        if ($request->expectsJson()) {
            return response()->json(['mensaje' => $mensaje], $estado);
        }

        return back()->with($estado === 200 ? 'success' : 'error', $mensaje);
    }
}
