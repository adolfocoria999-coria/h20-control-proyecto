<?php

namespace App\Services;

use App\Models\Ajuste;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * QR de cobro de la OTB: la imagen vive en el disco `public` (los socios la ven)
 * y su ruta y fecha de vencimiento se guardan en `ajustes`.
 */
class QrPago
{
    public const CLAVE_RUTA = 'qr_ruta';

    public const CLAVE_VENCE = 'qr_vence_el';

    // Días antes del vencimiento en que se empieza a alertar
    public const DIAS_AVISO = 7;

    public const SIN_QR = 'sin_qr';

    public const SIN_FECHA = 'sin_fecha';

    public const VENCIDO = 'vencido';

    public const POR_VENCER = 'por_vencer';

    public const VIGENTE = 'vigente';

    public function __construct(
        public readonly ?string $ruta,
        public readonly ?Carbon $venceEl,
    ) {}

    public static function actual(): self
    {
        $vence = Ajuste::valor(self::CLAVE_VENCE);

        return new self(
            ruta: Ajuste::valor(self::CLAVE_RUTA),
            venceEl: $vence ? Carbon::parse($vence)->startOfDay() : null,
        );
    }

    /**
     * Reemplaza la imagen (si se envía una nueva) y actualiza la fecha de vencimiento.
     */
    public static function guardar(?UploadedFile $imagen, string $venceEl): self
    {
        $anterior = self::actual();

        if ($imagen) {
            // Nombre único: evita que el navegador muestre el QR viejo desde caché
            $ruta = $imagen->storeAs('qr', 'qr_otb_'.now()->format('YmdHis').'.'.$imagen->extension(), 'public');
            Ajuste::guardar(self::CLAVE_RUTA, $ruta);

            if ($anterior->ruta && $anterior->ruta !== $ruta) {
                Storage::disk('public')->delete($anterior->ruta);
            }
        }

        Ajuste::guardar(self::CLAVE_VENCE, Carbon::parse($venceEl)->toDateString());

        return self::actual();
    }

    public function existe(): bool
    {
        return $this->ruta !== null && Storage::disk('public')->exists($this->ruta);
    }

    public function url(): ?string
    {
        return $this->existe() ? asset('storage/'.$this->ruta) : null;
    }

    /**
     * Días que faltan para el vencimiento (negativo si ya venció).
     */
    public function diasRestantes(): ?int
    {
        return $this->venceEl ? (int) today()->diffInDays($this->venceEl, false) : null;
    }

    public function estado(): string
    {
        return match (true) {
            ! $this->existe() => self::SIN_QR,
            $this->venceEl === null => self::SIN_FECHA,
            $this->diasRestantes() < 0 => self::VENCIDO,
            $this->diasRestantes() <= self::DIAS_AVISO => self::POR_VENCER,
            default => self::VIGENTE,
        };
    }

    // El QR se puede mostrar a los socios (existe y no está vencido)
    public function disponible(): bool
    {
        return in_array($this->estado(), [self::VIGENTE, self::POR_VENCER, self::SIN_FECHA], true);
    }

    public function requiereAtencion(): bool
    {
        return $this->estado() !== self::VIGENTE;
    }

    /**
     * Mensaje para los administradores según el estado.
     */
    public function mensaje(): string
    {
        $dias = $this->diasRestantes();

        return match ($this->estado()) {
            self::SIN_QR => 'No hay un QR de pago cargado. Los socios no pueden pagar por QR.',
            self::SIN_FECHA => 'El QR de pago no tiene fecha de vencimiento registrada.',
            self::VENCIDO => 'El QR de pago venció el '.$this->venceEl->format('d/m/Y').'. Los socios ya no lo ven: sube uno nuevo.',
            self::POR_VENCER => match ($dias) {
                0 => 'El QR de pago vence hoy.',
                1 => 'El QR de pago vence mañana.',
                default => "El QR de pago vence en {$dias} días ({$this->venceEl->format('d/m/Y')}).",
            },
            default => 'QR vigente hasta el '.$this->venceEl->format('d/m/Y').'.',
        };
    }
}
