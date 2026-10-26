<?php

namespace App\Services;

use App\Models\Ajuste;
use App\Models\SuscripcionPush;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use RuntimeException;
use Throwable;

/**
 * Notificaciones en el celular (Web Push, gratis): las entregan los servicios
 * de Google, Apple o Mozilla a quien instaló la app y aceptó recibirlas.
 *
 * Las claves VAPID identifican al sistema ante esos servicios: se toman de
 * WEBPUSH_PUBLIC_KEY / WEBPUSH_PRIVATE_KEY o, si no están, se generan una vez y
 * se guardan en `ajustes`.
 */
class NotificacionPush
{
    public const CLAVE_PUBLICA = 'webpush_clave_publica';

    public const CLAVE_PRIVADA = 'webpush_clave_privada';

    /**
     * @return array{publicKey: string, privateKey: string}
     */
    public static function claves(): array
    {
        $publica = config('services.webpush.public_key') ?: Ajuste::valor(self::CLAVE_PUBLICA);
        $privada = config('services.webpush.private_key') ?: Ajuste::valor(self::CLAVE_PRIVADA);

        if (! $publica || ! $privada) {
            ['publicKey' => $publica, 'privateKey' => $privada] = VAPID::createVapidKeys();
            Ajuste::guardar(self::CLAVE_PUBLICA, $publica);
            Ajuste::guardar(self::CLAVE_PRIVADA, $privada);
        }

        return ['publicKey' => $publica, 'privateKey' => $privada];
    }

    /**
     * Envía la misma notificación a todos los celulares de esos usuarios.
     *
     * @param  iterable<User>|User  $usuarios
     * @return array{enviadas: int, fallidas: int}
     */
    public function enviar(iterable|User $usuarios, string $titulo, string $cuerpo, string $url): array
    {
        $ids = collect($usuarios instanceof User ? [$usuarios] : $usuarios)->pluck('id');
        $mensaje = ['titulo' => $titulo, 'cuerpo' => $cuerpo, 'url' => $url];

        return $this->enviarVarias(SuscripcionPush::whereIn('user_id', $ids)->get()
            ->map(fn ($s) => [$s, $mensaje]));
    }

    /**
     * Envía una notificación distinta a cada suscripción, todas juntas.
     *
     * @param  Collection<int, array{0: SuscripcionPush, 1: array{titulo: string, cuerpo: string, url: string}}>  $envios
     * @return array{enviadas: int, fallidas: int}
     */
    public function enviarVarias(Collection $envios): array
    {
        if ($envios->isEmpty()) {
            return ['enviadas' => 0, 'fallidas' => 0];
        }

        try {
            $vencidas = $this->despachar($envios->map(fn ($envio) => [
                $envio[0],
                json_encode([
                    'title' => $envio[1]['titulo'],
                    'body' => $envio[1]['cuerpo'],
                    'url' => $envio[1]['url'],
                    'icon' => asset('iconos/icono-192.png'),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ])->all());
        } catch (Throwable $e) {
            // Una notificación que no sale nunca debe impedir registrar lecturas
            report($e);

            return ['enviadas' => 0, 'fallidas' => $envios->count()];
        }

        // El celular desinstaló la app o revocó el permiso: se deja de enviarle
        SuscripcionPush::whereIn('endpoint_hash', array_map([SuscripcionPush::class, 'hash'], $vencidas))->delete();

        return ['enviadas' => $envios->count() - count($vencidas), 'fallidas' => count($vencidas)];
    }

    /**
     * Entrega las notificaciones (en paralelo). Devuelve los endpoints que ya no existen.
     *
     * @param  list<array{0: SuscripcionPush, 1: string}>  $envios
     * @return list<string>
     */
    protected function despachar(array $envios): array
    {
        // Con tiempo límite: sin él, si Google/Mozilla no responden, la petición queda colgada para siempre
        $webPush = new WebPush(
            ['VAPID' => self::claves() + ['subject' => config('app.url')]],
            ['TTL' => 86400],
            new Client(['connect_timeout' => 5, 'timeout' => 10]),
        );

        foreach ($envios as [$suscripcion, $contenido]) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $suscripcion->endpoint,
                'publicKey' => $suscripcion->clave_p256dh,
                'authToken' => $suscripcion->clave_auth,
            ]), $contenido);
        }

        $vencidas = [];
        foreach ($webPush->flush() as $informe) {
            if ($informe->isSubscriptionExpired()) {
                $vencidas[] = $informe->getEndpoint();
            } elseif (! $informe->isSuccess()) {
                report(new RuntimeException('Web Push: '.$informe->getReason()));
            }
        }

        return $vencidas;
    }
}
