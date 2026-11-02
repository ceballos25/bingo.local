<?php

final class BalotaController
{
    public function __construct(
        private BalotaService $service
    ) {
    }

    public function sacar(): void
    {
        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada)) {
            $this->responder(
                ['error' => 'Debes enviar un cuerpo JSON válido.'],
                400
            );

            return;
        }

        if (!isset($entrada['partidaId']) || !is_int($entrada['partidaId'])) {
            $this->responder(
                ['error' => 'Debes enviar partidaId como entero.'],
                400
            );

            return;
        }

        try {
            $resultado = $this->service->sacar(
                $entrada['partidaId'],
                AuthContext::id()
            );

            $this->responder($resultado, 201);

        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log(
                'balotas/sacar: '
                . $exception->getMessage()
                . ' @ '
                . $exception->getFile()
                . ':'
                . $exception->getLine()
            );

            $this->responder(
                ['error' => 'No fue posible sacar la balota.'],
                500
            );
        }
    }

    public function listar(): void
    {
        $partidaId = filter_input(
            INPUT_GET,
            'partidaId',
            FILTER_VALIDATE_INT
        );

        if ($partidaId === false || $partidaId === null) {
            $this->responder(
                ['error' => 'Debes enviar partidaId como entero.'],
                400
            );

            return;
        }

        try {
            $resultado = $this->service->listarPorPartida($partidaId);
            $this->responder($resultado, 200);

        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible listar las balotas.'],
                500
            );
        }
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
