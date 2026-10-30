<?php

final class ReclamoService
{
    public function __construct(
        private PDO $database,
        private ReclamoRepository $reclamoRepository,
        private CartonRepository $cartonRepository,
        private PartidaRepository $partidaRepository,
        private BalotaRepository $balotaRepository,
        private UsuarioRepository $usuarioRepository,
        private CiudadRepository $ciudadRepository,
        private BingoValidador $bingoValidador,
        private PartidaRealtime $partidaRealtime,
        private MesaEstadoService $mesaEstadoService
    ) {
    }

    public function prevalidar(
        int $cartonId,
        int $usuarioId,
        string $modalidad
    ): array {
        $contexto = $this->prepararContextoCanto(
            $cartonId,
            $usuarioId,
            $modalidad,
            false
        );

        $cumple = $this->bingoValidador->cumple(
            $contexto['carton']['numeros'],
            $contexto['sacadas'],
            $contexto['modalidad']
        );

        return [
            'cumple' => $cumple,
            'partidaId' => $contexto['partidaId'],
            'cartonId' => $cartonId,
            'usuarioId' => $usuarioId,
            'modalidad' => $contexto['modalidad'],
            'premio' => $contexto['premio'],
            'botonesBloqueados' => $contexto['mesa']['botonesBloqueados'],
            'modalidadesCerradas' => $contexto['mesa']['modalidadesCerradas'],
            'mensaje' => $cumple
                ? 'El cartón cumple la modalidad; puedes cantar.'
                : 'El cartón no cumple la modalidad reclamada.',
        ];
    }

    public function cantar(
        int $cartonId,
        int $usuarioId,
        string $modalidad
    ): array {
        $contexto = $this->prepararContextoCanto(
            $cartonId,
            $usuarioId,
            $modalidad,
            true
        );

        if (!$this->bingoValidador->cumple(
            $contexto['carton']['numeros'],
            $contexto['sacadas'],
            $contexto['modalidad']
        )) {
            throw new DomainException(
                'El cartón no cumple la modalidad reclamada.'
            );
        }

        $reclamo = new Reclamo(
            $contexto['partidaId'],
            $cartonId,
            $usuarioId,
            $contexto['modalidad'],
            $contexto['premio']
        );

        try {
            $this->database->beginTransaction();

            if (
                $this->reclamoRepository->buscarPendientePorPartida(
                    $contexto['partidaId'],
                    true
                ) !== null
            ) {
                throw new DomainException(
                    'Hay un reclamo pendiente: la mesa está bloqueada.'
                );
            }

            $id = $this->reclamoRepository->guardar($reclamo);
            $this->database->commit();
        } catch (DomainException $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        } catch (PDOException $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            if ($this->esConflictoModalidadActiva($exception)) {
                throw new DomainException(
                    'Ya hay un reclamo activo para esa modalidad.'
                );
            }

            throw $exception;
        }

        $guardado = $this->reclamoRepository->buscarPorId($id);

        if ($guardado === null) {
            throw new RuntimeException(
                'No fue posible recuperar el reclamo creado.'
            );
        }

        $detalle = $this->detalleReclamo($guardado, $contexto['carton']);
        $mesa = $this->mesaEstadoService->snapshot($contexto['partidaId']);

        $this->partidaRealtime->publicar(
            $contexto['partidaId'],
            'reclamo_pendiente',
            [
                'mesa' => $mesa,
                'reclamo' => $detalle,
            ]
        );

        return $detalle;
    }

    public function aprobar(int $reclamoId, int $administradorId): array
    {
        return $this->resolver($reclamoId, $administradorId, 'aprobar');
    }

    public function rechazar(int $reclamoId, int $administradorId): array
    {
        return $this->resolver($reclamoId, $administradorId, 'rechazar');
    }

    public function listarPorPartida(int $partidaId): array
    {
        if ($partidaId <= 0) {
            throw new InvalidArgumentException(
                'El ID de la partida debe ser mayor que cero.'
            );
        }

        if ($this->partidaRepository->buscarPorId($partidaId) === null) {
            throw new DomainException('La partida no existe.');
        }

        $reclamos = $this->reclamoRepository->listarPorPartida($partidaId);
        $salida = [];

        foreach ($reclamos as $reclamo) {
            $carton = $this->cartonRepository->buscarPorId(
                (int) $reclamo['cartonId']
            );
            $salida[] = $this->detalleReclamo(
                $reclamo,
                $carton ?? ['id' => $reclamo['cartonId'], 'numeros' => []]
            );
        }

        return [
            'partidaId' => $partidaId,
            'total' => count($salida),
            'mesa' => $this->mesaEstadoService->snapshot($partidaId),
            'reclamos' => $salida,
        ];
    }

    /**
     * @return array{
     *   partidaId:int,
     *   modalidad:string,
     *   premio:float,
     *   carton:array,
     *   sacadas:list<int>,
     *   mesa:array
     * }
     */
    private function prepararContextoCanto(
        int $cartonId,
        int $usuarioId,
        string $modalidad,
        bool $exigirMesaLibre
    ): array {
        if ($cartonId <= 0 || $usuarioId <= 0) {
            throw new InvalidArgumentException(
                'cartonId y usuarioId deben ser mayores que cero.'
            );
        }

        $modalidad = trim($modalidad);
        $carton = $this->cartonRepository->buscarPorId($cartonId);

        if ($carton === null) {
            throw new DomainException('El cartón no existe.');
        }

        if (($carton['estado'] ?? '') !== 'vendido') {
            throw new DomainException(
                'Solo se puede cantar con un cartón vendido.'
            );
        }

        if ((int) ($carton['usuarioId'] ?? 0) !== $usuarioId) {
            throw new DomainException(
                'El cartón no pertenece a ese usuario.'
            );
        }

        $partidaId = (int) ($carton['partidaId'] ?? 0);

        if ($partidaId <= 0) {
            throw new DomainException(
                'El cartón no está asociado a una partida.'
            );
        }

        $partida = $this->partidaRepository->buscarPorId($partidaId);

        if ($partida === null) {
            throw new DomainException('La partida no existe.');
        }

        if (($partida['estado'] ?? '') !== 'en_curso') {
            throw new DomainException(
                'Solo se puede cantar en una partida en curso.'
            );
        }

        $usuario = $this->usuarioRepository->buscarPorId($usuarioId);

        if ($usuario === null) {
            throw new DomainException('El usuario no existe.');
        }

        if ((int) $usuario['organizacionId'] !== (int) $partida['organizacionId']) {
            throw new DomainException(
                'El usuario no pertenece a la organización de la partida.'
            );
        }

        if (($usuario['estado'] ?? '') !== 'activo') {
            throw new DomainException('El usuario está suspendido.');
        }

        $premio = $this->premioDeModalidad($partida, $modalidad);
        $mesa = $this->mesaEstadoService->snapshot($partidaId);

        if (
            $exigirMesaLibre
            && $mesa['botonesBloqueados']
        ) {
            throw new DomainException(
                'Hay un reclamo pendiente: la mesa está bloqueada.'
            );
        }

        if (in_array($modalidad, $mesa['modalidadesCerradas'], true)) {
            throw new DomainException(
                'Esa modalidad ya tiene ganador en esta partida.'
            );
        }

        return [
            'partidaId' => $partidaId,
            'modalidad' => $modalidad,
            'premio' => $premio,
            'carton' => $carton,
            'sacadas' => $this->balotaRepository->numerosSacados($partidaId),
            'mesa' => $mesa,
        ];
    }

    private function resolver(
        int $reclamoId,
        int $administradorId,
        string $accion
    ): array {
        if ($reclamoId <= 0 || $administradorId <= 0) {
            throw new InvalidArgumentException(
                'reclamoId y administradorId deben ser mayores que cero.'
            );
        }

        $fila = $this->reclamoRepository->buscarPorId($reclamoId);

        if ($fila === null) {
            throw new DomainException('El reclamo no existe.');
        }

        $partida = $this->partidaRepository->buscarPorId(
            (int) $fila['partidaId']
        );

        if ($partida === null) {
            throw new DomainException('La partida no existe.');
        }

        if (($partida['estado'] ?? '') !== 'en_curso') {
            throw new DomainException(
                'Solo se puede resolver un reclamo de una partida en curso.'
            );
        }

        $this->asegurarAdministrador(
            $administradorId,
            (int) $partida['organizacionId']
        );

        $carton = $this->cartonRepository->buscarPorId((int) $fila['cartonId']);

        if ($carton === null) {
            throw new DomainException('El cartón no existe.');
        }

        $reclamo = $this->desdeFila($fila);

        if ($accion === 'aprobar') {
            $sacadas = $this->balotaRepository->numerosSacados(
                (int) $fila['partidaId']
            );

            if (!$this->bingoValidador->cumple(
                $carton['numeros'],
                $sacadas,
                (string) $fila['modalidad']
            )) {
                throw new DomainException(
                    'El cartón no cumple la modalidad; no se puede aprobar.'
                );
            }

            $reclamo->aprobar($administradorId);
            $tipoEvento = 'reclamo_aprobado';
        } else {
            $reclamo->rechazar($administradorId);
            $tipoEvento = 'reclamo_rechazado';
        }

        $this->reclamoRepository->actualizar($reclamo);

        $actualizado = $this->reclamoRepository->buscarPorId($reclamoId);

        if ($actualizado === null) {
            throw new RuntimeException(
                'No fue posible recuperar el reclamo actualizado.'
            );
        }

        $detalle = $this->detalleReclamo($actualizado, $carton);
        $mesa = $this->mesaEstadoService->snapshot((int) $fila['partidaId']);

        $this->partidaRealtime->publicar(
            (int) $fila['partidaId'],
            $tipoEvento,
            [
                'mesa' => $mesa,
                'reclamo' => $detalle,
            ]
        );

        return $detalle;
    }

    private function detalleReclamo(array $fila, array $carton): array
    {
        $usuario = $this->usuarioRepository->buscarPorId(
            (int) $fila['usuarioId']
        );

        $ciudadNombre = null;
        $departamento = null;

        if ($usuario !== null && isset($usuario['ciudadId'])) {
            $ciudad = $this->ciudadRepository->buscarConDepartamento(
                (int) $usuario['ciudadId']
            );

            if ($ciudad !== null) {
                $ciudadNombre = $ciudad['ciudad'] ?? null;
                $departamento = $ciudad['departamento'] ?? null;
            }
        }

        $base = $this->aDto($fila)->toArray();

        $base['jugador'] = [
            'id' => (int) $fila['usuarioId'],
            'nombre' => $usuario['nombre'] ?? null,
            'apellido' => $usuario['apellido'] ?? null,
            'ciudad' => $ciudadNombre,
            'departamento' => $departamento,
        ];

        $base['carton'] = [
            'id' => (int) ($carton['id'] ?? $fila['cartonId']),
            'numeros' => $carton['numeros'] ?? [],
        ];

        $base['mesa'] = $this->mesaEstadoService->snapshot(
            (int) $fila['partidaId']
        );

        return $base;
    }

    private function premioDeModalidad(array $partida, string $modalidad): float
    {
        foreach ($partida['modalidades'] ?? [] as $item) {
            if (($item['modalidad'] ?? '') === $modalidad) {
                return (float) $item['premio'];
            }
        }

        throw new DomainException(
            'La partida no tiene configurada esa modalidad.'
        );
    }

    private function asegurarAdministrador(
        int $administradorId,
        int $organizacionId
    ): void {
        $admin = $this->usuarioRepository->buscarPorId($administradorId);

        if ($admin === null) {
            throw new DomainException(
                'El administrador no existe.'
            );
        }

        if ((int) $admin['organizacionId'] !== $organizacionId) {
            throw new DomainException(
                'El administrador no pertenece a la organización.'
            );
        }

        if (($admin['estado'] ?? '') !== 'activo') {
            throw new DomainException(
                'El administrador está suspendido.'
            );
        }

        if (($admin['rol'] ?? '') !== 'administrador') {
            throw new DomainException(
                'Solo un administrador puede validar reclamos.'
            );
        }
    }

    private function esConflictoModalidadActiva(PDOException $exception): bool
    {
        $codigo = (int) ($exception->errorInfo[1] ?? 0);

        return $codigo === 1062;
    }

    private function desdeFila(array $fila): Reclamo
    {
        return new Reclamo(
            (int) $fila['partidaId'],
            (int) $fila['cartonId'],
            (int) $fila['usuarioId'],
            (string) $fila['modalidad'],
            (float) $fila['premio'],
            (string) $fila['estado'],
            $fila['activo'] === null ? null : (int) $fila['activo'],
            $fila['administradorId'] === null
                ? null
                : (int) $fila['administradorId'],
            (int) $fila['id'],
            $fila['cantadoEn'] ?? null,
            $fila['resueltoEn'] ?? null
        );
    }

    private function aDto(array $fila): ReclamoResponseDTO
    {
        return new ReclamoResponseDTO(
            (int) $fila['id'],
            (int) $fila['partidaId'],
            (int) $fila['cartonId'],
            (int) $fila['usuarioId'],
            (string) $fila['modalidad'],
            (float) $fila['premio'],
            (string) $fila['estado'],
            $fila['administradorId'] === null
                ? null
                : (int) $fila['administradorId'],
            (string) $fila['cantadoEn'],
            $fila['resueltoEn'] ?? null
        );
    }
}
