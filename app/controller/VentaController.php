<?php

final class VentaController
{
    public function __construct(
        private VentaService $service
    ) {
    }

    public function manual(): void
    {
        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada)) {
            $this->responder(
                ['error' => 'Debes enviar un cuerpo JSON válido.'],
                400
            );

            return;
        }

        foreach (['cartonId', 'usuarioId'] as $campo) {
            if (!isset($entrada[$campo]) || !is_int($entrada[$campo])) {
                $this->responder(
                    ['error' => "Debes enviar {$campo} como entero."],
                    400
                );

                return;
            }
        }

        try {
            $resultado = $this->service->ventaManual(
                $entrada['cartonId'],
                $entrada['usuarioId'],
                AuthContext::id()
            );

            $this->responderVenta($resultado, 201);

        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible registrar la venta manual.'],
                500
            );
        }
    }

    public function gratis(): void
    {
        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada)) {
            $this->responder(
                ['error' => 'Debes enviar un cuerpo JSON válido.'],
                400
            );

            return;
        }

        $cartonIds = [];

        if (isset($entrada['cartonIds']) && is_array($entrada['cartonIds'])) {
            foreach ($entrada['cartonIds'] as $id) {
                if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
                    $this->responder(
                        ['error' => 'cartonIds debe ser una lista de enteros.'],
                        400
                    );

                    return;
                }

                $cartonIds[] = (int) $id;
            }
        } elseif (isset($entrada['cartonId']) && is_int($entrada['cartonId'])) {
            $cartonIds[] = $entrada['cartonId'];
        } else {
            $this->responder(
                ['error' => 'Debes enviar cartonId o cartonIds.'],
                400
            );

            return;
        }

        try {
            $resultados = $this->service->ventaGratis(
                $cartonIds,
                AuthContext::id()
            );

            if (count($resultados) === 1) {
                $this->responderVenta($resultados[0], 201);

                return;
            }

            $this->responder(
                [
                    'ok' => true,
                    'cantidad' => count($resultados),
                    'ventas' => array_map(
                        static fn (array $r): array => $r,
                        $resultados
                    ),
                ],
                201
            );
        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible asignar los cartones gratis.'],
                500
            );
        }
    }

    public function pse(): void
    {
        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada)) {
            $this->responder(
                ['error' => 'Debes enviar un cuerpo JSON válido.'],
                400
            );

            return;
        }

        $cartonIds = [];

        if (isset($entrada['cartonIds']) && is_array($entrada['cartonIds'])) {
            foreach ($entrada['cartonIds'] as $id) {
                if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
                    $this->responder(
                        ['error' => 'cartonIds debe ser una lista de enteros.'],
                        400
                    );

                    return;
                }

                $cartonIds[] = (int) $id;
            }
        } elseif (isset($entrada['cartonId']) && is_int($entrada['cartonId'])) {
            $cartonIds[] = $entrada['cartonId'];
        } else {
            $this->responder(
                ['error' => 'Debes enviar cartonId o cartonIds.'],
                400
            );

            return;
        }

        if (
            !isset($entrada['redirectUrl']) ||
            !is_string($entrada['redirectUrl'])
        ) {
            $this->responder(
                ['error' => 'Debes enviar redirectUrl como texto.'],
                400
            );

            return;
        }

        try {
            $resultado = $this->service->iniciarPse(
                $cartonIds,
                AuthContext::id(),
                $entrada['redirectUrl']
            );

            $this->responderVenta($resultado, 201);

        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (RuntimeException $exception) {
            error_log($exception->getMessage());

            $mensaje = $exception->getMessage();

            // Mensaje útil si OpenPay rechaza (order_id duplicado, credenciales, etc.).
            if (str_starts_with($mensaje, 'OpenPay')) {
                $this->responder(['error' => $mensaje], 502);

                return;
            }

            $this->responder(
                ['error' => 'No fue posible iniciar el pago PSE.'],
                500
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible iniciar el pago PSE.'],
                500
            );
        }
    }

    public function retornoPse(): void
    {
        $id = $_GET['id'] ?? null;

        if (!is_string($id) || trim($id) === '') {
            $this->responder(
                ['error' => 'Debes enviar id del cargo PSE (?id=...).'],
                400
            );

            return;
        }

        try {
            $resultado = $this->service->retornoPse($id);

            $dto = new VentaResponseDTO(
                $resultado['pago'],
                $resultado['carton'],
                $resultado['partida']
            );

            $payload = $dto->toArray();
            $payload['mensaje'] = $resultado['mensaje'];

            $this->responder($payload, 200);

        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible consultar el retorno del pago.'],
                500
            );
        }
    }

    public function webhookOpenpay(): void
    {
        if (!$this->autorizarWebhookOpenpay()) {
            $this->responder(
                ['error' => 'No autorizado.'],
                401
            );

            return;
        }

        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada)) {
            $this->responder(
                ['error' => 'Debes enviar un cuerpo JSON válido.'],
                400
            );

            return;
        }

        try {
            $resultado = $this->service->procesarWebhookPasarela($entrada);

            $this->responder($resultado, 200);

        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            // OpenPay reintenta si no recibe 200; logueamos pero respondemos 200
            // solo cuando el fallo es de negocio no recuperable? Mejor 500 para retry.
            $this->responder(
                ['error' => 'No fue posible procesar el webhook.'],
                500
            );
        }
    }

    public function comprobante(): void
    {
        $cartonId = filter_input(INPUT_POST, 'cartonId', FILTER_VALIDATE_INT);

        if ($cartonId === false || $cartonId === null) {
            $this->responder(
                ['error' => 'Debes enviar cartonId como entero.'],
                400
            );

            return;
        }

        if (!isset($_FILES['comprobante']) || !is_array($_FILES['comprobante'])) {
            $this->responder(
                ['error' => 'Debes enviar el archivo comprobante (form-data).'],
                400
            );

            return;
        }

        try {
            $resultado = $this->service->solicitarComprobante(
                $cartonId,
                AuthContext::id(),
                $_FILES['comprobante']
            );

            $this->responderVenta($resultado, 201);

        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible registrar el comprobante.'],
                500
            );
        }
    }

    public function listar(): void
    {
        $estado = filter_input(INPUT_GET, 'estado', FILTER_UNSAFE_RAW);
        $estado = is_string($estado) ? trim($estado) : null;
        $partidaId = filter_input(INPUT_GET, 'partidaId', FILTER_VALIDATE_INT);

        try {
            $lista = $this->service->listarParaMesa(
                AuthContext::organizacionId(),
                $estado !== '' ? $estado : null,
                ($partidaId !== false && $partidaId !== null) ? (int) $partidaId : null
            );

            $this->responder(['pagos' => $lista], 200);
        } catch (InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible listar los pagos.'],
                500
            );
        }
    }

    public function aprobarComprobante(): void
    {
        $this->resolverDecisionComprobante('aprobar');
    }

    public function rechazarComprobante(): void
    {
        $this->resolverDecisionComprobante('rechazar');
    }

    private function resolverDecisionComprobante(string $accion): void
    {
        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada)) {
            $this->responder(
                ['error' => 'Debes enviar un cuerpo JSON válido.'],
                400
            );

            return;
        }

        $pagoIdRaw = $entrada['pagoId'] ?? null;
        $pagoId = is_int($pagoIdRaw)
            ? $pagoIdRaw
            : (is_string($pagoIdRaw) && ctype_digit($pagoIdRaw)
                ? (int) $pagoIdRaw
                : 0);

        if ($pagoId <= 0) {
            $this->responder(
                ['error' => 'Debes enviar pagoId como entero.'],
                400
            );

            return;
        }

        try {
            if ($accion === 'aprobar') {
                $resultado = $this->service->aprobarComprobante(
                    $pagoId,
                    AuthContext::id()
                );
            } else {
                $resultado = $this->service->rechazarComprobante(
                    $pagoId,
                    AuthContext::id()
                );
            }

            $this->responderVenta($resultado, 200);

        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible procesar el comprobante.'],
                500
            );
        }
    }

    private function autorizarWebhookOpenpay(): bool
    {
        $usuarioEsperado = (string) ($_ENV['OPENPAY_WEBHOOK_USER'] ?? '');
        $passwordEsperado = (string) ($_ENV['OPENPAY_WEBHOOK_PASS'] ?? '');

        if ($usuarioEsperado === '' || $passwordEsperado === '') {
            return false;
        }

        $usuario = $_SERVER['PHP_AUTH_USER'] ?? null;
        $password = $_SERVER['PHP_AUTH_PW'] ?? null;

        if ($usuario === null || $password === null) {
            $header = $_SERVER['HTTP_AUTHORIZATION']
                ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
                ?? '';

            if (preg_match('/^Basic\s+(.+)$/i', trim($header), $coincidencias)) {
                $decoded = base64_decode($coincidencias[1], true);

                if (is_string($decoded) && str_contains($decoded, ':')) {
                    [$usuario, $password] = explode(':', $decoded, 2);
                }
            }
        }

        return hash_equals($usuarioEsperado, (string) $usuario)
            && hash_equals($passwordEsperado, (string) $password);
    }

    private function responderVenta(array $resultado, int $codigo): void
    {
        $dto = new VentaResponseDTO(
            $resultado['pago'],
            $resultado['carton'],
            $resultado['partida']
        );

        $payload = $dto->toArray();

        if (isset($resultado['pse']) && is_array($resultado['pse'])) {
            $payload['pse'] = $resultado['pse'];
        }

        $this->responder($payload, $codigo);
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
