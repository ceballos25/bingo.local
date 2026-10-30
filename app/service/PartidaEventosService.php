<?php

final class PartidaEventosService
{
    private const MAX_SEGUNDOS = 25;

    public function __construct(
        private PartidaRepository $partidaRepository,
        private PartidaEventoRepository $eventoRepository,
        private MesaEstadoService $mesaEstadoService
    ) {
    }

    public function snapshotInicial(int $partidaId): array
    {
        $this->asegurarPartida($partidaId);

        return [
            'partidaId' => $partidaId,
            'ultimoEventoId' => $this->eventoRepository->ultimoId($partidaId),
            'mesa' => $this->mesaEstadoService->snapshot($partidaId),
        ];
    }

    /**
     * Stream SSE (bloquea el proceso PHP hasta desconexión o timeout).
     */
    public function transmitirSse(int $partidaId, int $desdeId): void
    {
        $this->asegurarPartida($partidaId);

        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', 'off');
        @ini_set('implicit_flush', '1');

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-store');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        ignore_user_abort(false);
        set_time_limit(0);

        // desdeId=0 → solo en vivo (sin reenviar historial: balotas/cantadas ya vienen del REST).
        // Reconexiones pasan el último id y no pierden eventos.
        $ultimoId = $desdeId > 0
            ? $desdeId
            : $this->eventoRepository->ultimoId($partidaId);
        $inicio = time();

        $this->enviarSse('mesa', [
            'partidaId' => $partidaId,
            'mesa' => $this->mesaEstadoService->snapshot($partidaId),
            'ultimoEventoId' => $ultimoId,
        ], $ultimoId);

        while (!connection_aborted()) {
            if ((time() - $inicio) >= self::MAX_SEGUNDOS) {
                $this->enviarSse('timeout', [
                    'partidaId' => $partidaId,
                    'reconectarConDesdeId' => $ultimoId,
                ], $ultimoId);
                break;
            }

            $eventos = $this->eventoRepository->listarDesde(
                $partidaId,
                $ultimoId
            );

            foreach ($eventos as $evento) {
                $ultimoId = (int) $evento['id'];
                $this->enviarSse(
                    $evento['tipo'],
                    $evento['payload'],
                    $ultimoId
                );
            }

            if ($eventos === []) {
                echo ": ping\n\n";
                $this->flushSalida();
            }

            usleep(700000);
        }
    }

    private function asegurarPartida(int $partidaId): void
    {
        if ($partidaId <= 0) {
            throw new InvalidArgumentException(
                'El ID de la partida debe ser mayor que cero.'
            );
        }

        if ($this->partidaRepository->buscarPorId($partidaId) === null) {
            throw new DomainException('La partida no existe.');
        }
    }

    private function enviarSse(string $evento, array $data, int $id): void
    {
        echo 'id: ' . $id . "\n";
        echo 'event: ' . $evento . "\n";
        echo 'data: ' . json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ) . "\n\n";
        $this->flushSalida();
    }

    private function flushSalida(): void
    {
        if (function_exists('ob_flush')) {
            @ob_flush();
        }

        flush();
    }
}
