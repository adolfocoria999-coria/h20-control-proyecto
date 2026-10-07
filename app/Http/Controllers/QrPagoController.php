<?php

namespace App\Http\Controllers;

use App\Services\Historial;
use App\Services\QrPago;
use Illuminate\Http\Request;

class QrPagoController extends Controller
{
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
