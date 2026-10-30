<?php

final class PushController
{
    public function __construct(
        private PushService $service
    ) {
    }

    public function vapidPublica(): void
    {
        $clave = $this->service->clavePublica();

        if ($clave === null) {
            $this->responder(
                ['error' => 'Web Push no está configurado (VAPID).'],
                503
            );

            return;
        }

        $this->responder(['publicKey' => $clave], 200);
    }

    public function suscribir(): void
    {
        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada)) {
            $this->responder(
                ['error' => 'Debes enviar un cuerpo JSON válido.'],
                400
            );

            return;
        }

        $endpoint = $entrada['endpoint'] ?? null;
        $keys = $entrada['keys'] ?? null;

        if (!is_string($endpoint) || !is_array($keys)) {
            $this->responder(
                ['error' => 'Debes enviar endpoint y keys.'],
                400
            );

            return;
        }

        $p256dh = $keys['p256dh'] ?? null;
        $auth = $keys['auth'] ?? null;

        if (!is_string($p256dh) || !is_string($auth)) {
            $this->responder(
                ['error' => 'Debes enviar keys.p256dh y keys.auth.'],
                400
            );

            return;
        }

        try {
            $this->service->suscribir(
                AuthContext::id(),
                $endpoint,
                $p256dh,
                $auth
            );

            $this->responder(['ok' => true], 201);

        } catch (InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible guardar la suscripción.'],
                500
            );
        }
    }

    public function desuscribir(): void
    {
        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada)) {
            $this->responder(
                ['error' => 'Debes enviar un cuerpo JSON válido.'],
                400
            );

            return;
        }

        if (!isset($entrada['endpoint']) || !is_string($entrada['endpoint'])) {
            $this->responder(
                ['error' => 'Debes enviar endpoint como texto.'],
                400
            );

            return;
        }

        try {
            $this->service->desuscribir(
                AuthContext::id(),
                $entrada['endpoint']
            );

            $this->responder(['ok' => true], 200);

        } catch (InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible eliminar la suscripción.'],
                500
            );
        }
    }

    private function responder(array $datos, int $codigoHttp): void
    {
        http_response_code($codigoHttp);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}
