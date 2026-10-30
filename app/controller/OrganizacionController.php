<?php

final class OrganizacionController
{
    public function __construct(
        private OrganizacionService $service
    ) {
    }

    public function crear(): void
    {
        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada) || !isset($entrada['nombre']) || !is_string($entrada['nombre'])) {
            $this->responder(
                ['error' => 'Debes enviar el nombre en el cuerpo JSON.'],
                400
            );

            return;
        }

        try {
            $id = $this->service->crear($entrada['nombre']);

            $organizacion = $this->service->buscarPorId($id);

            if ($organizacion === null) {
                throw new RuntimeException(
                    'No se encontró la organización recién creada.'
                );
            }

            $dto = new OrganizacionResponseDTO(
                (int) $organizacion['id'],
                $organizacion['nombre'],
                $organizacion['estado'],
                $organizacion['creadoEn'],
                $organizacion['modificadoEn']
            );

            $this->responder($dto->toArray(), 201);

        } catch (DomainException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible crear la organización.'],
                500
            );
        }
    }

    public function resumen(): void
    {
        try {
            $organizacionId = AuthContext::organizacionId();
            $resumen = $this->service->resumen($organizacionId);
            $this->responder($resumen, 200);
        } catch (InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (DomainException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                404
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible obtener el resumen.'],
                500
            );
        }
    }

    public function listar(): void
    {
        try {
            $organizaciones = $this->service->listarTodos();

            $respuesta = [];

            foreach ($organizaciones as $organizacion) {
                $dto = new OrganizacionResponseDTO(
                    (int) $organizacion['id'],
                    $organizacion['nombre'],
                    $organizacion['estado'],
                    $organizacion['creadoEn'],
                    $organizacion['modificadoEn']
                );

                $respuesta[] = $dto->toArray();
            }

            $this->responder($respuesta, 200);

        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible listar las organizaciones.'],
                500
            );
        }
    }

    public function buscarPorId(int $id): void
    {
        if ($id <= 0) {
            $this->responder(
                ['error' => 'El ID debe ser mayor que cero.'],
                400
            );

            return;
        }

        try {
            $organizacion = $this->service->buscarPorId($id);

            if ($organizacion === null) {
                $this->responder(
                    ['error' => 'Organización no encontrada.'],
                    404
                );

                return;
            }

            $dto = new OrganizacionResponseDTO(
                (int) $organizacion['id'],
                $organizacion['nombre'],
                $organizacion['estado'],
                $organizacion['creadoEn'],
                $organizacion['modificadoEn']
            );

            $this->responder($dto->toArray(), 200);

        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible consultar la organización.'],
                500
            );
        }
    }

    public function actualizar(int $id): void
    {
        if ($id <= 0) {
            $this->responder(
                ['error' => 'El ID debe ser mayor que cero.'],
                400
            );

            return;
        }

        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada)) {
            $this->responder(
                ['error' => 'Debes enviar el nombre o el estado en el cuerpo JSON.'],
                400
            );

            return;
        }

        $nombre = null;
        $estado = null;

        if (array_key_exists('nombre', $entrada)) {
            if (!is_string($entrada['nombre'])) {
                $this->responder(
                    ['error' => 'El nombre debe ser un texto.'],
                    400
                );

                return;
            }

            $nombre = $entrada['nombre'];
        }

        if (array_key_exists('estado', $entrada)) {
            if (!is_string($entrada['estado'])) {
                $this->responder(
                    ['error' => 'El estado debe ser un texto.'],
                    400
                );

                return;
            }

            $estado = $entrada['estado'];
        }

        if ($nombre === null && $estado === null) {
            $this->responder(
                ['error' => 'Debes enviar el nombre o el estado en el cuerpo JSON.'],
                400
            );

            return;
        }

        try {
            $organizacion = $this->service->actualizar($id, $nombre, $estado);

            if ($organizacion === null) {
                $this->responder(
                    ['error' => 'Organización no encontrada.'],
                    404
                );

                return;
            }

            $dto = new OrganizacionResponseDTO(
                (int) $organizacion['id'],
                $organizacion['nombre'],
                $organizacion['estado'],
                $organizacion['creadoEn'],
                $organizacion['modificadoEn']
            );

            $this->responder($dto->toArray(), 200);

        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible actualizar la organización.'],
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
