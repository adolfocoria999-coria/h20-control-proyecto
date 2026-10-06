<?php

namespace App\View\Components;

use App\Services\QrPago;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Aviso en todas las pantallas para Superadmin/Hacienda cuando el QR de pago
 * falta, no tiene fecha, está por vencer o ya venció.
 */
class AlertaQr extends Component
{
    public QrPago $qr;

    public function __construct()
    {
        $this->qr = QrPago::actual();
    }

    public function shouldRender(): bool
    {
        return (bool) auth()->user()?->esAdmin() && $this->qr->requiereAtencion();
    }

    public function render(): View
    {
        return view('components.alerta-qr');
    }
}
