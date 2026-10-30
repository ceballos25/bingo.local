<?php

/**
 * Fan-out de eventos de partida:
 * 1) bitácora MySQL (partida_eventos)
 * 2) push Centrifugo (si está habilitado)
 *
 * Canal: partida:{id}
 */
final class PartidaRealtime
{
    public function __construct(
        private PartidaEventoRepository $eventoRepository,
        private CentrifugoPublisher $centrifugoPublisher,
        private ?PushService $pushService = null
    ) {
    }

    public function publicar(
        int $partidaId,
        string $tipo,
        array $payload
    ): void {
        $this->eventoRepository->publicar($partidaId, $tipo, $payload);
        $this->centrifugoPublisher->publish($partidaId, $tipo, $payload);

        if ($this->pushService !== null) {
            try {
                $this->pushService->notificarPartida(
                    $partidaId,
                    $tipo,
                    $payload
                );
            } catch (Throwable $exception) {
                error_log('Push partida: ' . $exception->getMessage());
            }
        }
    }

    public static function canal(int $partidaId): string
    {
        return 'partida:' . $partidaId;
    }
}
