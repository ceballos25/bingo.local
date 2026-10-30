<?php

final class RealtimeController
{
    public function config(): void
    {
        $enabled = ($_ENV['CENTRIFUGO_ENABLED'] ?? '') === '1'
            || strtolower((string) ($_ENV['CENTRIFUGO_ENABLED'] ?? '')) === 'true';

        $driver = $enabled ? 'centrifugo' : 'sse';

        $this->responder([
            'driver' => $driver,
            'sseUrl' => '/partidas/eventos',
            'wsUrl' => $enabled
                ? (string) ($_ENV['CENTRIFUGO_WS_URL'] ?? '')
                : null,
            'canalPlantilla' => 'partida:{id}',
        ], 200);
    }

    private function responder(
        array $datos,
        int $codigoHttp
    ): void {
        http_response_code($codigoHttp);

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(
            $datos,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}
