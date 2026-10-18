<?php

namespace App\Http\Controllers;

use App\Services\Historial;
use App\Services\QrPago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class QrPagoController extends Controller
{
    /**
     * Imagen del QR. Fuera de Hacienda y el superadmin, solo se ve mientras esté disponible (no vencida).
     */
    public function imagen(Request $request)
    {
        $qr = QrPago::actual();

        abort_unless($qr->existe(), 404, 'No hay un QR de pago cargado.');
        abort_if(! $request->user()->esAdmin() && ! $qr->disponible(), 404, 'El QR de pago no está disponible.');

        return Storage::disk('archivos')->response($qr->ruta, 'qr_otb.'.pathinfo($qr->ruta, PATHINFO_EXTENSION));
    }

    /**
     * Sube un QR nuevo y/o cambia su fecha de vencimiento (Superadmin y Hacienda).
     */
    public function update(Request $request)
    {
        $existe = QrPago::actual()->existe();

        $request->validateWithBag('qr', [
            // La imagen es obligatoria solo la primera vez; luego se puede cambiar solo la fecha
            'qr_imagen' => [$existe ? 'nullable' : 'required', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
            'qr_vence_el' => 'required|date|after_or_equal:today',
        ], [
            'qr_imagen.required' => 'Debes subir la imagen del QR.',
            'qr_vence_el.after_or_equal' => 'La fecha de vencimiento no puede ser anterior a hoy.',
        ]);

        $anterior = QrPago::actual();
        $nuevo = QrPago::guardar($request->file('qr_imagen'), $request->qr_vence_el);

        $cambios = array_filter([
            'imagen' => $request->hasFile('qr_imagen') ? ['antes' => $anterior->ruta, 'despues' => $nuevo->ruta] : null,
            'vence_el' => $anterior->venceEl?->toDateString() !== $nuevo->venceEl?->toDateString()
                ? ['antes' => $anterior->venceEl?->toDateString(), 'despues' => $nuevo->venceEl?->toDateString()]
                : null,
        ]);
        Historial::registrar(
            'actualizado',
            'qr',
            ($request->hasFile('qr_imagen') ? 'Subió un nuevo QR de pago' : 'Cambió la fecha de vencimiento del QR de pago')
                .' (vence el '.$nuevo->venceEl->format('d/m/Y').')',
            cambios: $cambios,
        );

        return redirect()->to(route('finanzas.index').'#qr')
            ->with('success', 'QR de pago actualizado correctamente ✓');
    }
}
