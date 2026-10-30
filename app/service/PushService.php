<?php

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Web Push (VAPID) para la PWA del jugador.
 */
final class PushService
{
    public function __construct(
        private PushSubscriptionRepository $repository,
        private CartonRepository $cartonRepository
    ) {
    }

    public function clavePublica(): ?string
    {
        $clave = trim((string) ($_ENV['VAPID_PUBLIC_KEY'] ?? ''));

        return $clave !== '' ? $clave : null;
    }

    public function suscribir(
        int $usuarioId,
        string $endpoint,
        string $p256dh,
        string $auth
    ): void {
        $endpoint = trim($endpoint);
        $p256dh = trim($p256dh);
        $auth = trim($auth);

        if ($usuarioId <= 0) {
            throw new InvalidArgumentException(
                'El usuarioId debe ser mayor que cero.'
            );
        }

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            throw new InvalidArgumentException(
                'Debes enviar endpoint, p256dh y auth.'
            );
        }

        if (mb_strlen($endpoint) > 512) {
            throw new InvalidArgumentException(
                'El endpoint es demasiado largo.'
            );
        }

        $this->repository->guardar($usuarioId, $endpoint, $p256dh, $auth);
    }

    public function desuscribir(int $usuarioId, string $endpoint): void
    {
        $endpoint = trim($endpoint);

        if ($endpoint === '') {
            throw new InvalidArgumentException(
                'Debes enviar endpoint.'
            );
        }

        $this->repository->eliminarPorEndpoint($endpoint, $usuarioId);
    }

    public function notificarPartida(
        int $partidaId,
        string $tipo,
        array $payload
    ): void {
        if ($this->clavePublica() === null) {
            return;
        }

        $privado = trim((string) ($_ENV['VAPID_PRIVATE_KEY'] ?? ''));
        $subject = trim((string) ($_ENV['VAPID_SUBJECT'] ?? 'mailto:admin@bingo.local'));

        if ($privado === '') {
            return;
        }

        $cartones = $this->cartonRepository->listarPorPartida($partidaId);
        $usuarioIds = [];

        foreach ($cartones as $carton) {
            $uid = $carton['usuarioId'] ?? null;

            if ($uid !== null && (int) $uid > 0) {
                $usuarioIds[] = (int) $uid;
            }
        }

        $subs = $this->repository->listarPorUsuarioIds($usuarioIds);

        if ($subs === []) {
            return;
        }

        $titulo = $this->tituloParaTipo($tipo);
        $cuerpo = $this->cuerpoParaTipo($tipo, $payload);

        $mensaje = json_encode([
            'title' => $titulo,
            'body' => $cuerpo,
            'data' => [
                'tipo' => $tipo,
                'partidaId' => $partidaId,
                'payload' => $payload,
            ],
        ], JSON_UNESCAPED_UNICODE);

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => $subject,
                    'publicKey' => $this->clavePublica(),
                    'privateKey' => $privado,
                ],
            ]);
        } catch (Throwable $exception) {
            error_log('WebPush init: ' . $exception->getMessage());

            return;
        }

        foreach ($subs as $sub) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $sub['endpoint'],
                    'keys' => [
                        'p256dh' => $sub['p256dh'],
                        'auth' => $sub['auth'],
                    ],
                ]);

                $webPush->queueNotification($subscription, $mensaje);
            } catch (Throwable $exception) {
                error_log('WebPush queue: ' . $exception->getMessage());
            }
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            $codigo = $report->getResponse()?->getStatusCode();

            if ($codigo === 404 || $codigo === 410) {
                $this->repository->eliminarPorEndpoint(
                    $report->getEndpoint()
                );
            } else {
                error_log(
                    'WebPush fail: ' . $report->getReason()
                );
            }
        }
    }

    private function tituloParaTipo(string $tipo): string
    {
        return match ($tipo) {
            'balota' => 'Nueva balota',
            'reclamo_pendiente' => '¡Bingo cantado!',
            'reclamo_aprobado' => 'Bingo aprobado',
            'reclamo_rechazado' => 'Bingo rechazado',
            'partida_estado' => 'Partida actualizada',
            default => 'Bingo Virtual',
        };
    }

    private function cuerpoParaTipo(string $tipo, array $payload): string
    {
        if ($tipo === 'balota') {
            $etiqueta = $payload['etiqueta']
                ?? $payload['balota']['etiqueta']
                ?? null;

            return $etiqueta !== null
                ? 'Salió ' . $etiqueta
                : 'Hay una nueva balota en tu partida.';
        }

        if ($tipo === 'reclamo_pendiente') {
            return 'Un jugador cantó bingo. La mesa está en revisión.';
        }

        if ($tipo === 'reclamo_aprobado') {
            return 'Se aprobó un bingo. ¡Revisa modalidades!';
        }

        if ($tipo === 'reclamo_rechazado') {
            return 'Se rechazó un canto. La mesa continúa.';
        }

        return 'Hay novedades en tu bingo.';
    }
}
