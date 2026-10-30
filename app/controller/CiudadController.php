<?php

final class CiudadController
{
    public function __construct(
        private CiudadRepository $repository
    ) {
    }

    public function listar(): void
    {
        try {
            $ciudades = $this->repository->listarTodos();

            $this->responder($ciudades, 200);

        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible listar las ciudades.'],
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
