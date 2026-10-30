<?php

final class PartidaController
{
    public function __construct(
        private PartidaService $service,
        private ?PartidaEventosService $eventosService = null,
        private ?CartonRepository $cartonRepository = null
    ) {
    }

    public function crear(): void
    {
        $entrada = json_decode(file_get_contents('php://input'), true);

        if (!is_array($entrada)) {
            $this->responder(
                ['error' => 'Debes enviar un cuerpo JSON válido.'],
                400
            );

            return;
        }

        $enteros = [
            'numero',
            'cantidadCartones',
        ];

        foreach ($enteros as $campo) {
            if (!isset($entrada[$campo]) || !is_int($entrada[$campo])) {
                $this->responder(
                    ['error' => "Debes enviar {$campo} como entero."],
                    400
                );

                return;
            }
        }

        if (!isset($entrada['nombre']) || !is_string($entrada['nombre'])) {
            $this->responder(
                ['error' => 'Debes enviar nombre como texto.'],
                400
            );

            return;
        }

        if (!isset($entrada['fecha']) || !is_string($entrada['fecha'])) {
            $this->responder(
                ['error' => 'Debes enviar fecha como texto (YYYY-MM-DD HH:MM:SS).'],
                400
            );

            return;
        }

        if (
            !isset($entrada['precioCarton']) ||
            (!is_int($entrada['precioCarton']) && !is_float($entrada['precioCarton']))
        ) {
            $this->responder(
                ['error' => 'Debes enviar precioCarton como número.'],
                400
            );

            return;
        }

        if (
            !isset($entrada['modalidades']) ||
            !is_array($entrada['modalidades'])
        ) {
            $this->responder(
                ['error' => 'Debes enviar modalidades como arreglo.'],
                400
            );

            return;
        }

        $youtubeVideoId = null;

        if (array_key_exists('youtubeVideoId', $entrada)) {
            if (
                $entrada['youtubeVideoId'] !== null
                && !is_string($entrada['youtubeVideoId'])
            ) {
                $this->responder(
                    ['error' => 'Debes enviar youtubeVideoId como texto o null.'],
                    400
                );

                return;
            }

            $youtubeVideoId = $entrada['youtubeVideoId'];
        }

        $gratis = false;

        if (array_key_exists('gratis', $entrada)) {
            if (!is_bool($entrada['gratis'])) {
                $this->responder(
                    ['error' => 'Debes enviar gratis como booleano.'],
                    400
                );

                return;
            }

            $gratis = $entrada['gratis'];
        }

        try {
            $id = $this->service->crear(
                AuthContext::organizacionId(),
                AuthContext::id(),
                $entrada['nombre'],
                $entrada['numero'],
                $entrada['fecha'],
                (float) $entrada['precioCarton'],
                $entrada['cantidadCartones'],
                $entrada['modalidades'],
                $youtubeVideoId,
                $gratis
            );

            $partida = $this->service->buscarPorId($id);

            if ($partida === null) {
                throw new RuntimeException(
                    'No se encontró la partida recién creada.'
                );
            }

            $this->responder($this->aDto($partida)->toArray(), 201);

        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible crear la partida.'],
                500
            );
        }
    }

    public function listar(): void
    {
        try {
            $partidas = $this->service->listarPorOrganizacion(
                AuthContext::organizacionId()
            );

            $respuesta = [];

            foreach ($partidas as $partida) {
                $respuesta[] = $this->aDto($partida)->toArray();
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
                ['error' => 'No fue posible listar las partidas.'],
                500
            );
        }
    }

    /**
     * Landing pública: solo partidas pendiente / en_curso de una org.
     */
    public function listarPublicas(): void
    {
        $organizacionId = filter_input(
            INPUT_GET,
            'organizacionId',
            FILTER_VALIDATE_INT
        );

        if ($organizacionId === false) {
            $this->responder(
                ['error' => 'Debes enviar organizacionId como entero.'],
                400
            );

            return;
        }

        try {
            $organizacionId = $this->service->resolverOrganizacionPublica(
                (int) ($organizacionId ?? 0)
            );

            if ($organizacionId === 0) {
                $this->responder([], 200);

                return;
            }

            $partidas = $this->service->listarPorOrganizacion($organizacionId);
            $respuesta = [];

            foreach ($partidas as $partida) {
                $estado = (string) ($partida['estado'] ?? '');

                if ($estado !== 'pendiente' && $estado !== 'en_curso') {
                    continue;
                }

                $respuesta[] = $this->aDto($partida)->toArray();
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
                ['error' => 'No fue posible listar las partidas.'],
                500
            );
        }
    }

    /**
     * Vitrina sin JWT: partida abierta + cartones disponibles.
     */
    public function vitrina(): void
    {
        $id = filter_input(
            INPUT_GET,
            'id',
            FILTER_VALIDATE_INT
        );

        if ($id === false || $id === null || $id <= 0) {
            $this->responder(
                ['error' => 'Debes enviar id como entero.'],
                400
            );

            return;
        }

        try {
            $partida = $this->service->buscarPorId($id);

            if ($partida === null) {
                $this->responder(
                    ['error' => 'Partida no encontrada.'],
                    404
                );

                return;
            }

            $estado = (string) ($partida['estado'] ?? '');

            if ($estado !== 'pendiente' && $estado !== 'en_curso') {
                $this->responder(
                    ['error' => 'Esta partida ya no está a la venta.'],
                    404
                );

                return;
            }

            $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
            $perPage = filter_input(INPUT_GET, 'perPage', FILTER_VALIDATE_INT);

            if ($page === false || $page === null || $page < 1) {
                $page = 1;
            }

            if ($perPage === false || $perPage === null || $perPage < 1) {
                $perPage = 24;
            }

            $perPage = min(48, max(8, $perPage));

            $cartones = [];
            $total = 0;

            if ($this->cartonRepository !== null) {
                $total = $this->cartonRepository->contarDisponiblesPorPartida($id);
                $offset = ($page - 1) * $perPage;

                foreach (
                    $this->cartonRepository->listarDisponiblesPorPartida(
                        $id,
                        $perPage,
                        $offset
                    ) as $carton
                ) {
                    $cartones[] = (new CartonResponseDTO(
                        (int) $carton['id'],
                        (int) $carton['organizacionId'],
                        $carton['partidaId'] !== null
                            ? (int) $carton['partidaId']
                            : null,
                        $carton['usuarioId'] !== null
                            ? (int) $carton['usuarioId']
                            : null,
                        $carton['numeros'],
                        $carton['estado'],
                        $carton['creadoEn'],
                        $carton['modificadoEn']
                    ))->toArray();
                }
            }

            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

            if ($page > $totalPages) {
                $page = $totalPages;
            }

            $this->responder(
                [
                    'partida' => $this->aDto($partida)->toArray(),
                    'cartones' => $cartones,
                    'paginacion' => [
                        'page' => $page,
                        'perPage' => $perPage,
                        'total' => $total,
                        'totalPages' => $totalPages,
                    ],
                ],
                200
            );
        } catch (InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible cargar la vitrina.'],
                500
            );
        }
    }

    public function iniciar(): void
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
            $partida = $this->service->iniciar(
                $entrada['partidaId'],
                AuthContext::id()
            );

            $this->responder($this->aDto($partida)->toArray(), 200);

        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible iniciar la partida.'],
                500
            );
        }
    }

    public function finalizar(): void
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
            $partida = $this->service->finalizar(
                $entrada['partidaId'],
                AuthContext::id()
            );

            $this->responder($this->aDto($partida)->toArray(), 200);

        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible finalizar la partida.'],
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
            $partida = $this->service->buscarPorId($id);

            if ($partida === null) {
                $this->responder(
                    ['error' => 'Partida no encontrada.'],
                    404
                );

                return;
            }

            if (
                (int) $partida['organizacionId'] !== AuthContext::organizacionId()
            ) {
                $this->responder(
                    ['error' => 'Partida no encontrada.'],
                    404
                );

                return;
            }

            $this->responder($this->aDto($partida)->toArray(), 200);

        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible consultar la partida.'],
                500
            );
        }
    }

    public function eventos(): void
    {
        if ($this->eventosService === null) {
            $this->responder(
                ['error' => 'SSE no está configurado.'],
                500
            );

            return;
        }

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

        $desdeId = filter_input(
            INPUT_GET,
            'desdeId',
            FILTER_VALIDATE_INT
        );

        if ($desdeId === false || $desdeId === null) {
            $desdeId = 0;
        }

        // Jugador solo puede seguir la mesa si tiene cartón vendido en la partida.
        if (
            AuthContext::hayUsuario()
            && AuthContext::rol() === 'jugador'
            && $this->cartonRepository !== null
            && !$this->cartonRepository->jugadorTieneCartonVendidoEnPartida(
                AuthContext::id(),
                $partidaId
            )
        ) {
            $this->responder(
                [
                    'error' => 'Debes tener un cartón comprado (vendido) para entrar en vivo.',
                ],
                403
            );

            return;
        }

        try {
            $this->eventosService->transmitirSse($partidaId, $desdeId);
        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible abrir el stream de eventos.'],
                500
            );
        }
    }

    public function asignarYoutube(): void
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

        if (!array_key_exists('youtubeVideoId', $entrada)) {
            $this->responder(
                ['error' => 'Debes enviar youtubeVideoId (texto, URL o null).'],
                400
            );

            return;
        }

        if (
            $entrada['youtubeVideoId'] !== null
            && !is_string($entrada['youtubeVideoId'])
        ) {
            $this->responder(
                ['error' => 'Debes enviar youtubeVideoId como texto o null.'],
                400
            );

            return;
        }

        try {
            $partida = $this->service->asignarYoutube(
                $entrada['partidaId'],
                AuthContext::id(),
                $entrada['youtubeVideoId']
            );

            $this->responder($this->aDto($partida)->toArray(), 200);
        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible asignar el live de YouTube.'],
                500
            );
        }
    }

    public function marcarGratis(): void
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

        if (!isset($entrada['gratis']) || !is_bool($entrada['gratis'])) {
            $this->responder(
                ['error' => 'Debes enviar gratis como booleano.'],
                400
            );

            return;
        }

        $precioCarton = null;

        if (array_key_exists('precioCarton', $entrada)) {
            if (
                !is_int($entrada['precioCarton'])
                && !is_float($entrada['precioCarton'])
            ) {
                $this->responder(
                    ['error' => 'Debes enviar precioCarton como número.'],
                    400
                );

                return;
            }

            $precioCarton = (float) $entrada['precioCarton'];
        }

        try {
            $partida = $this->service->marcarGratis(
                $entrada['partidaId'],
                AuthContext::id(),
                $entrada['gratis'],
                $precioCarton
            );

            $this->responder($this->aDto($partida)->toArray(), 200);
        } catch (DomainException | InvalidArgumentException $exception) {
            $this->responder(
                ['error' => $exception->getMessage()],
                400
            );
        } catch (Throwable $exception) {
            error_log($exception->getMessage());

            $this->responder(
                ['error' => 'No fue posible actualizar el flag gratis.'],
                500
            );
        }
    }

    private function aDto(array $partida): PartidaResponseDTO
    {
        return new PartidaResponseDTO(
            (int) $partida['id'],
            (int) $partida['organizacionId'],
            (int) $partida['operadorId'],
            $partida['nombre'],
            (int) $partida['numero'],
            $partida['fecha'],
            (float) $partida['precioCarton'],
            !empty($partida['gratis']),
            (int) $partida['cantidadCartones'],
            (int) $partida['cartonesConfirmados'],
            $partida['estado'],
            $partida['youtubeVideoId'] ?? null,
            $partida['modalidades'],
            $partida['creadoEn'],
            $partida['modificadoEn']
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
