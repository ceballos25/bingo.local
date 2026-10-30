<?php

final class ReclamoController
{
    public function __construct(
        private ReclamoService $service
    ) {
    }

    public function prevalidar(): void
    {
        $this->resolverCanto('prevalidar');
    }

    public function cantar(): void
    {
        $this->resolverCanto('cantar');
    }

    private function resolverCanto(string $accion): void
    {
        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada)) {
            $this->responder(
                ['error' => 'Debes enviar un cuerpo JSON válido.'],
                400
            );

            return;
        }

        if (!isset($entrada['cartonId']) || !is_int($entrada['cartonId'])) {
            $this->responder(
                ['error' => 'Debes enviar cartonId como entero.'],
                400
            );

            return;
        }

        if (
            !isset($entrada['modalidad'])
            || !is_string($entrada['modalidad'])
        ) {
            $this->responder(
                ['error' => 'Debes enviar modalidad como texto.'],
                400
            );

            return;
        }

        try {
            if ($accion === 'prevalidar') {
                $resultado = $this->service->prevalidar(
                    $entrada['cartonId'],
                    AuthContext::id(),
                    $entrada['modalidad']
                );
                $this->responder($resultado, 200);
            } else {
                $resultado = $this->service->cantar(
                    $entrada['cartonId'],
                    AuthContext::id(),
                    $entrada['modalidad']
                );
                $this->responder($resultado, 201);
            }
        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                [
                    'error' => $accion === 'prevalidar'
                        ? 'No fue posible prevalidar el reclamo.'
                        : 'No fue posible registrar el reclamo.',
                ],
                500
            );
        }
    }

    public function aprobar(): void
    {
        $this->resolver('aprobar');
    }

    public function rechazar(): void
    {
        $this->resolver('rechazar');
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
                ['error' => 'No fue posible listar los reclamos.'],
                500
            );
        }
    }

    private function resolver(string $accion): void
    {
        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada)) {
            $this->responder(
                ['error' => 'Debes enviar un cuerpo JSON válido.'],
                400
            );

            return;
        }

        if (!isset($entrada['reclamoId']) || !is_int($entrada['reclamoId'])) {
            $this->responder(
                ['error' => 'Debes enviar reclamoId como entero.'],
                400
            );

            return;
        }

        try {
            if ($accion === 'aprobar') {
                $resultado = $this->service->aprobar(
                    $entrada['reclamoId'],
                    AuthContext::id()
                );
            } else {
                $resultado = $this->service->rechazar(
                    $entrada['reclamoId'],
                    AuthContext::id()
                );
            }

            $this->responder($resultado, 200);
        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible resolver el reclamo.'],
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
