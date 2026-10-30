<?php

final class CuentaPagoController
{
    public function __construct(
        private CuentaPagoService $service
    ) {
    }

    /** GET /cuentas-pago/publicas?organizacionId=1 (sin JWT). */
    public function listarPublicas(): void
    {
        $organizacionId = filter_input(
            INPUT_GET,
            'organizacionId',
            FILTER_VALIDATE_INT
        );

        if ($organizacionId === false || $organizacionId === null) {
            $this->responder(
                ['error' => 'Debes enviar organizacionId como entero.'],
                400
            );

            return;
        }

        try {
            $filas = $this->service->listarPublicas($organizacionId);
            $respuesta = [];

            foreach ($filas as $fila) {
                $respuesta[] = $this->dto($fila)->toPublicArray();
            }

            $this->responder($respuesta, 200);
        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(['error' => $exception->getMessage()], 400);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->responder(
                ['error' => 'No fue posible listar las cuentas de pago.'],
                500
            );
        }
    }

    /** GET /cuentas-pago/listar (admin, org del token). */
    public function listar(): void
    {
        try {
            $filas = $this->service->listarAdmin(AuthContext::organizacionId());
            $respuesta = [];

            foreach ($filas as $fila) {
                $respuesta[] = $this->dto($fila)->toArray();
            }

            $this->responder($respuesta, 200);
        } catch (DomainException $exception) {
            $this->responder(['error' => $exception->getMessage()], 403);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->responder(
                ['error' => 'No fue posible listar las cuentas.'],
                500
            );
        }
    }

    public function crear(): void
    {
        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada)) {
            $this->responder(['error' => 'JSON inválido.'], 400);

            return;
        }

        try {
            $fila = $this->service->crear(
                AuthContext::organizacionId(),
                $entrada
            );
            $this->responder($this->dto($fila)->toArray(), 201);
        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(['error' => $exception->getMessage()], 400);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->responder(
                ['error' => 'No fue posible crear la cuenta.'],
                500
            );
        }
    }

    public function actualizar(int $id): void
    {
        if ($id <= 0) {
            $this->responder(['error' => 'ID inválido.'], 400);

            return;
        }

        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada)) {
            $this->responder(['error' => 'JSON inválido.'], 400);

            return;
        }

        try {
            $fila = $this->service->actualizar(
                $id,
                AuthContext::organizacionId(),
                $entrada
            );
            $this->responder($this->dto($fila)->toArray(), 200);
        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(['error' => $exception->getMessage()], 400);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->responder(
                ['error' => 'No fue posible actualizar la cuenta.'],
                500
            );
        }
    }

    public function eliminar(int $id): void
    {
        if ($id <= 0) {
            $this->responder(['error' => 'ID inválido.'], 400);

            return;
        }

        try {
            $fila = $this->service->desactivar(
                $id,
                AuthContext::organizacionId()
            );
            $this->responder($this->dto($fila)->toArray(), 200);
        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(['error' => $exception->getMessage()], 400);
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->responder(
                ['error' => 'No fue posible desactivar la cuenta.'],
                500
            );
        }
    }

    private function dto(array $fila): CuentaPagoResponseDTO
    {
        return new CuentaPagoResponseDTO(
            (int) $fila['id'],
            (int) $fila['organizacionId'],
            (string) $fila['tipo'],
            (string) $fila['banco'],
            (string) $fila['marca'],
            (string) $fila['valor'],
            (string) $fila['etiqueta'],
            (string) $fila['titular'],
            (int) $fila['orden'],
            (bool) $fila['activo'],
            $fila['creadoEn'] ?? null,
            $fila['modificadoEn'] ?? null
        );
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
