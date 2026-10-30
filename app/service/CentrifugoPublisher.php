<?php

/**
 * Publica eventos de mesa hacia Centrifugo (HTTP API).
 * Si CENTRIFUGO_ENABLED no está activo, no hace nada (útil en hosting sin realtime).
 */
final class CentrifugoPublisher
{
    public function publish(int $partidaId, string $tipo, array $payload): void
    {
        $enabled = ($_ENV['CENTRIFUGO_ENABLED'] ?? '') === '1'
            || strtolower((string) ($_ENV['CENTRIFUGO_ENABLED'] ?? '')) === 'true';

        if (!$enabled) {
            return;
        }

        $apiUrl = rtrim(
            (string) ($_ENV['CENTRIFUGO_API_URL'] ?? 'http://127.0.0.1:8000'),
            '/'
        );
        $apiKey = (string) ($_ENV['CENTRIFUGO_API_KEY'] ?? '');

        if ($apiKey === '') {
            error_log('Centrifugo: falta CENTRIFUGO_API_KEY.');
            return;
        }

        $canal = 'partida:' . $partidaId;
        $data = array_merge(
            [
                'tipo' => $tipo,
                'partidaId' => $partidaId,
            ],
            $payload
        );

        $cuerpo = json_encode(
            [
                'channel' => $canal,
                'data' => $data,
            ],
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        $ch = curl_init($apiUrl . '/api/publish');

        if ($ch === false) {
            error_log('Centrifugo: no se pudo iniciar cURL.');
            return;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-API-Key: ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => $cuerpo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 1,
        ]);

        $respuesta = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($respuesta === false || $codigo < 200 || $codigo >= 300) {
            error_log(
                'Centrifugo publish falló'
                . ' canal=' . $canal
                . ' http=' . $codigo
                . ' error=' . $error
                . ' body=' . substr((string) $respuesta, 0, 300)
            );
        }
    }
}
