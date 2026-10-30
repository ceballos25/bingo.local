<?php

final class PartidaService
{
    public function __construct(
        private PDO $database,
        private PartidaRepository $partidaRepository,
        private CartonRepository $cartonRepository,
        private CartonGenerator $generator,
        private OrganizacionRepository $organizacionRepository,
        private UsuarioRepository $usuarioRepository
    ) {
    }

    /**
     * @param array<int, array{modalidad: string, premio: float|int|string}> $modalidades
     */
    public function crear(
        int $organizacionId,
        int $operadorId,
        string $nombre,
        int $numero,
        string $fecha,
        float $precioCarton,
        int $cantidadCartones,
        array $modalidades,
        ?string $youtubeVideoId = null,
        bool $gratis = false
    ): int {
        $this->asegurarOrganizacionExiste($organizacionId);
        $this->asegurarOperadorValido($operadorId, $organizacionId);
        $this->asegurarNumeroDisponible($organizacionId, $numero);

        $modalidadEntidades = [];

        foreach ($modalidades as $item) {
            if (
                !is_array($item) ||
                !isset($item['modalidad'], $item['premio'])
            ) {
                throw new InvalidArgumentException(
                    'Cada modalidad debe incluir modalidad y premio.'
                );
            }

            $modalidadEntidades[] = new PartidaModalidad(
                (string) $item['modalidad'],
                (float) $item['premio']
            );
        }

        $partida = new Partida(
            $organizacionId,
            $operadorId,
            $nombre,
            $numero,
            $fecha,
            $precioCarton,
            $cantidadCartones,
            $modalidadEntidades,
            null,
            'pendiente',
            0,
            $youtubeVideoId,
            $gratis
        );

        try {
            $this->database->beginTransaction();

            $partidaId = $this->partidaRepository->guardar($partida);

            for ($i = 0; $i < $partida->getCantidadCartones(); $i++) {
                $carton = new Carton(
                    $partida->getOrganizacionId(),
                    $this->generator->generarNumeros(),
                    null,
                    'disponible',
                    null,
                    $partidaId
                );

                $this->cartonRepository->guardar($carton);
            }

            $this->database->commit();

            return $partidaId;

        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }

    public function listarTodos(): array
    {
        return $this->normalizarLista(
            $this->partidaRepository->listarTodos()
        );
    }

    /** Sin org explícita: la única organización activa, o 0 si hay varias. */
    public function resolverOrganizacionPublica(int $organizacionId): int
    {
        if ($organizacionId > 0) {
            return $organizacionId;
        }

        $unica = $this->organizacionRepository->buscarUnicaActiva();

        return $unica !== null ? (int) $unica['id'] : 0;
    }

    public function listarPorOrganizacion(int $organizacionId): array
    {
        if ($organizacionId <= 0) {
            throw new InvalidArgumentException(
                'El ID de la organización debe ser mayor que cero.'
            );
        }

        return $this->normalizarLista(
            $this->partidaRepository->listarPorOrganizacion($organizacionId)
        );
    }

    public function buscarPorId(int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'El ID debe ser mayor que cero.'
            );
        }

        $partida = $this->partidaRepository->buscarPorId($id);

        if ($partida === null) {
            return null;
        }

        return $this->normalizarFila($partida);
    }

    public function iniciar(int $partidaId, int $operadorId): array
    {
        $fila = $this->obtenerPartidaParaOperador($partidaId, $operadorId);
        $partida = $this->aEntidad($fila);
        $partida->iniciar();
        $this->partidaRepository->actualizarEstado($partida);

        $actualizada = $this->partidaRepository->buscarPorId($partidaId);

        return $this->normalizarFila($actualizada);
    }

    public function finalizar(int $partidaId, int $operadorId): array
    {
        $fila = $this->obtenerPartidaParaOperador($partidaId, $operadorId);
        $partida = $this->aEntidad($fila);
        $partida->finalizar();
        $this->partidaRepository->actualizarEstado($partida);

        $actualizada = $this->partidaRepository->buscarPorId($partidaId);

        return $this->normalizarFila($actualizada);
    }

    public function asignarYoutube(
        int $partidaId,
        int $operadorId,
        ?string $youtubeVideoId
    ): array {
        $fila = $this->obtenerPartidaParaOperador($partidaId, $operadorId);
        $partida = $this->aEntidad($fila);
        $partida->asignarYoutubeVideoId($youtubeVideoId);
        $this->partidaRepository->actualizarYoutubeVideoId($partida);

        $actualizada = $this->partidaRepository->buscarPorId($partidaId);

        return $this->normalizarFila($actualizada);
    }

    private function obtenerPartidaParaOperador(
        int $partidaId,
        int $operadorId
    ): array {
        if ($partidaId <= 0) {
            throw new InvalidArgumentException(
                'El ID de la partida debe ser mayor que cero.'
            );
        }

        $fila = $this->partidaRepository->buscarPorId($partidaId);

        if ($fila === null) {
            throw new DomainException(
                'La partida no existe.'
            );
        }

        $this->asegurarOperadorValido(
            $operadorId,
            (int) $fila['organizacionId']
        );

        return $fila;
    }

    private function aEntidad(array $fila): Partida
    {
        $modalidades = [];

        foreach ($fila['modalidades'] ?? [] as $item) {
            $modalidades[] = new PartidaModalidad(
                (string) $item['modalidad'],
                (float) $item['premio']
            );
        }

        return new Partida(
            (int) $fila['organizacionId'],
            (int) $fila['operadorId'],
            $fila['nombre'],
            (int) $fila['numero'],
            $fila['fecha'],
            (float) $fila['precioCarton'],
            (int) $fila['cantidadCartones'],
            $modalidades,
            (int) $fila['id'],
            $fila['estado'],
            (int) $fila['cartonesConfirmados'],
            $fila['youtubeVideoId'] ?? null,
            !empty($fila['gratis']),
            $fila['creadoEn'] ?? null,
            $fila['modificadoEn'] ?? null
        );
    }

    public function marcarGratis(
        int $partidaId,
        int $operadorId,
        bool $gratis,
        ?float $precioCarton = null
    ): array {
        $fila = $this->obtenerPartidaParaOperador($partidaId, $operadorId);
        $partida = $this->aEntidad($fila);
        $partida->marcarGratis($gratis, $precioCarton);
        $this->partidaRepository->actualizarGratis($partida);

        $actualizada = $this->partidaRepository->buscarPorId($partidaId);

        if ($actualizada === null) {
            throw new DomainException('La partida no existe.');
        }

        return $this->normalizarFila($actualizada);
    }

    private function asegurarOrganizacionExiste(int $organizacionId): void
    {
        if ($organizacionId <= 0) {
            throw new InvalidArgumentException(
                'El ID de la organización debe ser mayor que cero.'
            );
        }

        if ($this->organizacionRepository->buscarPorId($organizacionId) === null) {
            throw new DomainException(
                'La organización no existe.'
            );
        }
    }

    private function asegurarOperadorValido(
        int $operadorId,
        int $organizacionId
    ): void {
        if ($operadorId <= 0) {
            throw new InvalidArgumentException(
                'El ID del operador debe ser mayor que cero.'
            );
        }

        $operador = $this->usuarioRepository->buscarPorId($operadorId);

        if ($operador === null) {
            throw new DomainException(
                'El operador no existe.'
            );
        }

        if ((int) $operador['organizacionId'] !== $organizacionId) {
            throw new DomainException(
                'El operador no pertenece a la organización.'
            );
        }

        if (($operador['estado'] ?? '') !== 'activo') {
            throw new DomainException(
                'El operador está suspendido.'
            );
        }

        $rol = $operador['rol'] ?? '';

        if ($rol !== 'administrador' && $rol !== 'operador') {
            throw new DomainException(
                'Solo un administrador u operador puede crear partidas.'
            );
        }
    }

    private function asegurarNumeroDisponible(
        int $organizacionId,
        int $numero
    ): void {
        $existente = $this->partidaRepository->buscarPorOrganizacionYNumero(
            $organizacionId,
            $numero
        );

        if ($existente !== null) {
            throw new DomainException(
                'Ya existe una partida con ese número en esta organización.'
            );
        }
    }

    private function normalizarLista(array $partidas): array
    {
        foreach ($partidas as &$partida) {
            $partida = $this->normalizarFila($partida);
        }

        unset($partida);

        return $partidas;
    }

    private function normalizarFila(array $partida): array
    {
        $partida['id'] = (int) $partida['id'];
        $partida['organizacionId'] = (int) $partida['organizacionId'];
        $partida['operadorId'] = (int) $partida['operadorId'];
        $partida['numero'] = (int) $partida['numero'];
        $partida['precioCarton'] = (float) $partida['precioCarton'];
        $partida['gratis'] = !empty($partida['gratis']);
        $partida['cantidadCartones'] = (int) $partida['cantidadCartones'];
        $partida['cartonesConfirmados'] = (int) $partida['cartonesConfirmados'];
        $partida['youtubeVideoId'] = $partida['youtubeVideoId'] ?? null;

        return $partida;
    }
}
