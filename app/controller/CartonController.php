<?php

final class CartonController
{
    public function __construct(
        private CartonService $service,
        private PagoRepository $pagoRepository
    ) {
    }

    public function crear(): void
    {
        try {
            $id = $this->service->crearAutomatico(AuthContext::organizacionId());

            $carton = $this->service->buscarPorId($id);

            if ($carton === null) {
                throw new RuntimeException(
                    'No se encontró el cartón recién creado.'
                );
            }

            $this->responder($this->aDto($carton)->toArray(), 201);

        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible crear el cartón.'],
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

        try {
            if ($partidaId !== false && $partidaId !== null) {
                $cartones = $this->service->listarPorPartida($partidaId);
                $orgId = AuthContext::organizacionId();
                $cartones = array_values(array_filter(
                    $cartones,
                    static fn (array $c): bool => (int) ($c['organizacionId'] ?? 0)
                        === $orgId
                ));

                // Jugador: solo sus cartones (no los de otros en la misma partida).
                if (AuthContext::rol() === 'jugador') {
                    $usuarioId = AuthContext::id();
                    $cartones = array_values(array_filter(
                        $cartones,
                        static fn (array $c): bool => (int) ($c['usuarioId'] ?? 0)
                            === $usuarioId
                    ));
                }
            } elseif (AuthContext::rol() === 'jugador') {
                $cartones = $this->service->listarPorUsuario(AuthContext::id());
            } else {
                $cartones = $this->service->listarPorOrganizacion(
                    AuthContext::organizacionId()
                );
            }

            $respuesta = [];

            foreach ($cartones as $carton) {
                $fila = $this->aDto($carton)->toArray();

                if (($fila['estado'] ?? '') === 'reservado') {
                    $pago = $this->pagoRepository->buscarPendientePorCarton(
                        (int) $fila['id']
                    );

                    if ($pago !== null) {
                        $fila['pagoCanal'] = $pago['canal'];
                        $fila['pagoEstado'] = $pago['estado'];
                    }
                }

                $respuesta[] = $fila;
            }

            $this->responder($respuesta, 200);

        } catch (InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible listar los cartones.'],
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
            $carton = $this->service->buscarPorId($id);

            if ($carton === null) {
                $this->responder(
                    ['error' => 'Cartón no encontrado.'],
                    404
                );

                return;
            }

            if (
                (int) ($carton['organizacionId'] ?? 0)
                !== AuthContext::organizacionId()
            ) {
                $this->responder(
                    ['error' => 'Cartón no encontrado.'],
                    404
                );

                return;
            }

            // Jugador no puede leer cartones de otro dueño.
            if (
                AuthContext::rol() === 'jugador'
                && (int) ($carton['usuarioId'] ?? 0) !== AuthContext::id()
            ) {
                $this->responder(
                    ['error' => 'Cartón no encontrado.'],
                    404
                );

                return;
            }

            $this->responder($this->aDto($carton)->toArray(), 200);

        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible consultar el cartón.'],
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

        if (!is_array($entrada) || !isset($entrada['estado']) || !is_string($entrada['estado'])) {
            $this->responder(
                ['error' => 'Debes enviar el estado en el cuerpo JSON.'],
                400
            );

            return;
        }

        $usuarioId = null;

        if (array_key_exists('usuarioId', $entrada)) {
            if (!is_int($entrada['usuarioId'])) {
                $this->responder(
                    ['error' => 'El usuarioId debe ser un entero.'],
                    400
                );

                return;
            }

            $usuarioId = $entrada['usuarioId'];
        }

        try {
            $carton = $this->service->actualizarEstado(
                $id,
                $entrada['estado'],
                $usuarioId
            );

            if ($carton === null) {
                $this->responder(
                    ['error' => 'Cartón no encontrado.'],
                    404
                );

                return;
            }

            $this->responder($this->aDto($carton)->toArray(), 200);

        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible actualizar el cartón.'],
                500
            );
        }
    }

    public function eliminar(int $id): void
    {
        if ($id <= 0) {
            $this->responder(
                ['error' => 'El ID debe ser mayor que cero.'],
                400
            );

            return;
        }

        try {
            $carton = $this->service->eliminar($id);

            if ($carton === null) {
                $this->responder(
                    ['error' => 'Cartón no encontrado.'],
                    404
                );

                return;
            }

            $this->responder($this->aDto($carton)->toArray(), 200);

        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible eliminar el cartón.'],
                500
            );
        }
    }

    private function aDto(array $carton): CartonResponseDTO
    {
        return new CartonResponseDTO(
            (int) $carton['id'],
            (int) $carton['organizacionId'],
            $carton['partidaId'] !== null ? (int) $carton['partidaId'] : null,
            $carton['usuarioId'] !== null ? (int) $carton['usuarioId'] : null,
            $carton['numeros'],
            $carton['estado'],
            $carton['creadoEn'],
            $carton['modificadoEn']
        );
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
