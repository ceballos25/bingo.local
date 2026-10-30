<?php

final class AuthController
{
    public function __construct(
        private AuthService $service
    ) {
    }

    public function login(): void
    {
        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada)) {
            $this->responder(
                ['error' => 'Debes enviar un cuerpo JSON válido.'],
                400
            );

            return;
        }

        if (
            isset($entrada['organizacionId']) &&
            !is_int($entrada['organizacionId'])
        ) {
            $this->responder(
                ['error' => 'Debes enviar organizacionId como entero.'],
                400
            );

            return;
        }

        if (
            !isset($entrada['identificador']) ||
            !is_string($entrada['identificador'])
        ) {
            $this->responder(
                ['error' => 'Debes enviar identificador como texto (correo o celular).'],
                400
            );

            return;
        }

        if (
            !isset($entrada['password']) ||
            !is_string($entrada['password'])
        ) {
            $this->responder(
                ['error' => 'Debes enviar password como texto.'],
                400
            );

            return;
        }

        try {
            $resultado = $this->service->login(
                $this->service->resolverOrganizacion(
                    (int) ($entrada['organizacionId'] ?? 0)
                ),
                $entrada['identificador'],
                $entrada['password']
            );

            $dto = new AuthResponseDTO(
                $resultado['token'],
                $resultado['usuario']
            );

            $this->responder($dto->toArray(), 200);

        } catch (InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (DomainException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                401
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible iniciar sesión.'],
                500
            );
        }
    }

    public function registro(): void
    {
        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada)) {
            $this->responder(
                ['error' => 'Debes enviar un cuerpo JSON válido.'],
                400
            );

            return;
        }

        if (!isset($entrada['ciudadId']) || !is_int($entrada['ciudadId'])) {
            $this->responder(
                ['error' => 'Debes enviar ciudadId como entero.'],
                400
            );

            return;
        }

        if (
            isset($entrada['organizacionId']) &&
            !is_int($entrada['organizacionId'])
        ) {
            $this->responder(
                ['error' => 'Debes enviar organizacionId como entero.'],
                400
            );

            return;
        }

        $textos = ['nombre', 'apellido', 'celular', 'correo', 'password'];

        foreach ($textos as $campo) {
            if (!isset($entrada[$campo]) || !is_string($entrada[$campo])) {
                $this->responder(
                    ['error' => "Debes enviar {$campo} como texto."],
                    400
                );

                return;
            }
        }

        try {
            $resultado = $this->service->registro(
                $this->service->resolverOrganizacion(
                    (int) ($entrada['organizacionId'] ?? 0)
                ),
                $entrada['ciudadId'],
                $entrada['nombre'],
                $entrada['apellido'],
                $entrada['celular'],
                $entrada['correo'],
                $entrada['password']
            );

            $dto = new AuthResponseDTO(
                $resultado['token'],
                $resultado['usuario']
            );

            $this->responder($dto->toArray(), 201);

        } catch (InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (DomainException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible crear la cuenta.'],
                500
            );
        }
    }

    public function invitado(): void
    {
        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada)) {
            $this->responder(
                ['error' => 'Debes enviar un cuerpo JSON válido.'],
                400
            );

            return;
        }

        if (
            isset($entrada['organizacionId']) &&
            !is_int($entrada['organizacionId'])
        ) {
            $this->responder(
                ['error' => 'Debes enviar organizacionId como entero.'],
                400
            );

            return;
        }

        $textos = ['nombre', 'apellido', 'celular', 'correo'];

        foreach ($textos as $campo) {
            if (!isset($entrada[$campo]) || !is_string($entrada[$campo])) {
                $this->responder(
                    ['error' => "Debes enviar {$campo} como texto."],
                    400
                );

                return;
            }
        }

        $ciudadId = 1;

        if (isset($entrada['ciudadId'])) {
            if (!is_int($entrada['ciudadId'])) {
                $this->responder(
                    ['error' => 'Debes enviar ciudadId como entero.'],
                    400
                );

                return;
            }

            $ciudadId = $entrada['ciudadId'];
        }

        try {
            $resultado = $this->service->invitado(
                $this->service->resolverOrganizacion(
                    (int) ($entrada['organizacionId'] ?? 0)
                ),
                $entrada['nombre'],
                $entrada['apellido'],
                $entrada['celular'],
                $entrada['correo'],
                $ciudadId,
                ($entrada['paraPagar'] ?? false) === true
            );

            if (!empty($resultado['requiereOtp'])) {
                $this->responder($resultado, 200);

                return;
            }

            $dto = new AuthResponseDTO(
                $resultado['token'],
                $resultado['usuario']
            );

            $payload = $dto->toArray();
            $payload['enlaceAcceso'] = $resultado['enlaceAcceso'];

            $this->responder($payload, 201);

        } catch (InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (DomainException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible crear el acceso de invitado.'],
                500
            );
        }
    }

    public function accesoInvitado(): void
    {
        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada)) {
            $this->responder(
                ['error' => 'Debes enviar un cuerpo JSON válido.'],
                400
            );

            return;
        }

        if (
            isset($entrada['organizacionId']) &&
            !is_int($entrada['organizacionId'])
        ) {
            $this->responder(
                ['error' => 'Debes enviar organizacionId como entero.'],
                400
            );

            return;
        }

        if (!isset($entrada['celular']) || !is_string($entrada['celular'])) {
            $this->responder(
                ['error' => 'Debes enviar celular como texto.'],
                400
            );

            return;
        }

        if (!isset($entrada['correo']) || !is_string($entrada['correo'])) {
            $this->responder(
                ['error' => 'Debes enviar correo como texto.'],
                400
            );

            return;
        }

        try {
            $resultado = $this->service->accesoInvitado(
                $this->service->resolverOrganizacion(
                    (int) ($entrada['organizacionId'] ?? 0)
                ),
                $entrada['celular'],
                $entrada['correo'],
                ($entrada['paraPagar'] ?? false) === true
            );

            if (!empty($resultado['requiereOtp'])) {
                $this->responder($resultado, 200);

                return;
            }

            $dto = new AuthResponseDTO(
                $resultado['token'],
                $resultado['usuario']
            );

            $payload = $dto->toArray();
            $payload['enlaceAcceso'] = $resultado['enlaceAcceso'];

            $this->responder($payload, 200);
        } catch (InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (DomainException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible renovar el acceso.'],
                500
            );
        }
    }

    public function solicitarOtpAdmin(): void
    {
        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada)) {
            $this->responder(
                ['error' => 'Debes enviar un cuerpo JSON válido.'],
                400
            );

            return;
        }

        if (
            isset($entrada['organizacionId']) &&
            !is_int($entrada['organizacionId'])
        ) {
            $this->responder(
                ['error' => 'Debes enviar organizacionId como entero.'],
                400
            );

            return;
        }

        if (!isset($entrada['correo']) || !is_string($entrada['correo'])) {
            $this->responder(
                ['error' => 'Debes enviar correo como texto.'],
                400
            );

            return;
        }

        try {
            $resultado = $this->service->solicitarOtpAdmin(
                $this->service->resolverOrganizacion(
                    (int) ($entrada['organizacionId'] ?? 0)
                ),
                $entrada['correo']
            );

            $this->responder($resultado, 200);
        } catch (InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (DomainException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible enviar el código.'],
                500
            );
        }
    }

    public function verificarOtpAdmin(): void
    {
        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada)) {
            $this->responder(
                ['error' => 'Debes enviar un cuerpo JSON válido.'],
                400
            );

            return;
        }

        if (
            isset($entrada['organizacionId']) &&
            !is_int($entrada['organizacionId'])
        ) {
            $this->responder(
                ['error' => 'Debes enviar organizacionId como entero.'],
                400
            );

            return;
        }

        if (!isset($entrada['correo']) || !is_string($entrada['correo'])) {
            $this->responder(
                ['error' => 'Debes enviar correo como texto.'],
                400
            );

            return;
        }

        if (!isset($entrada['codigo']) || !is_string($entrada['codigo'])) {
            $this->responder(
                ['error' => 'Debes enviar codigo como texto.'],
                400
            );

            return;
        }

        try {
            $resultado = $this->service->verificarOtpAdmin(
                $this->service->resolverOrganizacion(
                    (int) ($entrada['organizacionId'] ?? 0)
                ),
                $entrada['correo'],
                $entrada['codigo']
            );

            $dto = new AuthResponseDTO(
                $resultado['token'],
                $resultado['usuario']
            );

            $this->responder($dto->toArray(), 200);
        } catch (InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (DomainException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                401
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible verificar el código.'],
                500
            );
        }
    }

    public function yo(): void
    {
        $token = $this->extraerBearer();

        if ($token === null) {
            $this->responder(
                ['error' => 'Debes enviar el token Bearer.'],
                401
            );

            return;
        }

        try {
            $usuario = $this->service->yo($token);

            $dto = new UsuarioResponseDTO(
                (int) $usuario['id'],
                (int) $usuario['organizacionId'],
                (int) $usuario['ciudadId'],
                $usuario['nombre'],
                $usuario['apellido'],
                $usuario['celular'],
                $usuario['correo'],
                $usuario['rol'],
                $usuario['estado'],
                $usuario['creadoEn'],
                $usuario['modificadoEn']
            );

            $this->responder($dto->toArray(), 200);

        } catch (DomainException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                401
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible consultar la sesión.'],
                500
            );
        }
    }

    private function extraerBearer(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();

            foreach ($headers as $nombre => $valor) {
                if (strcasecmp($nombre, 'Authorization') === 0) {
                    $header = $valor;
                    break;
                }
            }
        }

        if (!preg_match('/^Bearer\s+(\S+)$/i', trim($header), $coincidencias)) {
            return null;
        }

        return $coincidencias[1];
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
