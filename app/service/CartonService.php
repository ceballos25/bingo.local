<?php

final class CartonService
{
    public function __construct(
        private CartonRepository $repository,
        private CartonGenerator $generator,
        private OrganizacionRepository $organizacionRepository,
        private UsuarioRepository $usuarioRepository
    ) {
    }

    public function crear(int $organizacionId, array $numbers): int
    {
        $this->asegurarOrganizacionExiste($organizacionId);

        $carton = new Carton($organizacionId, $numbers);

        return $this->repository->guardar($carton);
    }

    public function crearAutomatico(int $organizacionId): int
    {
        $numbers = $this->generator->generarNumeros();

        return $this->crear($organizacionId, $numbers);
    }

    public function listarTodos(): array
    {
        return $this->repository->listarTodos();
    }

    public function listarPorOrganizacion(int $organizacionId): array
    {
        if ($organizacionId <= 0) {
            throw new InvalidArgumentException(
                'El ID de la organización debe ser mayor que cero.'
            );
        }

        return $this->repository->listarPorOrganizacion($organizacionId);
    }

    public function listarPorUsuario(int $usuarioId): array
    {
        if ($usuarioId <= 0) {
            throw new InvalidArgumentException(
                'El ID del usuario debe ser mayor que cero.'
            );
        }

        return $this->repository->listarPorUsuario($usuarioId);
    }

    public function listarPorPartida(int $partidaId): array
    {
        if ($partidaId <= 0) {
            throw new InvalidArgumentException(
                'El ID de la partida debe ser mayor que cero.'
            );
        }

        return $this->repository->listarPorPartida($partidaId);
    }

    public function buscarPorId(int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'El ID debe ser mayor que cero.'
            );
        }

        return $this->repository->buscarPorId($id);
    }

    public function actualizarEstado(
        int $id,
        string $estado,
        ?int $usuarioId = null
    ): ?array {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'El ID debe ser mayor que cero.'
            );
        }

        $fila = $this->repository->buscarPorId($id);

        if ($fila === null) {
            return null;
        }

        $carton = $this->aEntidad($fila);

        if ($estado === $carton->getEstado() && $estado === 'disponible') {
            return $fila;
        }

        if ($estado === 'reservado') {
            $usuario = $this->obtenerUsuarioCompatible(
                $usuarioId,
                (int) $fila['organizacionId']
            );
            $carton->reservar((int) $usuario['id']);
        } elseif ($estado === 'vendido') {
            $usuario = $this->obtenerUsuarioCompatible(
                $usuarioId,
                (int) $fila['organizacionId']
            );
            $carton->vender((int) $usuario['id']);
        } elseif ($estado === 'disponible') {
            $carton->liberar();
        } else {
            throw new DomainException(
                'El estado debe ser disponible, reservado o vendido.'
            );
        }

        $this->repository->actualizar($carton);

        return $this->repository->buscarPorId($id);
    }

    public function eliminar(int $id): ?array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'El ID debe ser mayor que cero.'
            );
        }

        $fila = $this->repository->buscarPorId($id);

        if ($fila === null) {
            return null;
        }

        $carton = $this->aEntidad($fila);

        $carton->anular();

        $this->repository->actualizar($carton);

        return $this->repository->buscarPorId($id);
    }

    private function aEntidad(array $fila): Carton
    {
        return new Carton(
            (int) $fila['organizacionId'],
            $fila['numeros'],
            (int) $fila['id'],
            $fila['estado'],
            $fila['usuarioId'] !== null ? (int) $fila['usuarioId'] : null,
            $fila['partidaId'] !== null ? (int) $fila['partidaId'] : null
        );
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

    private function obtenerUsuarioCompatible(
        ?int $usuarioId,
        int $organizacionId
    ): array {
        if ($usuarioId === null) {
            throw new InvalidArgumentException(
                'Debes enviar usuarioId para reservar o vender el cartón.'
            );
        }

        if ($usuarioId <= 0) {
            throw new InvalidArgumentException(
                'El ID del usuario debe ser mayor que cero.'
            );
        }

        $usuario = $this->usuarioRepository->buscarPorId($usuarioId);

        if ($usuario === null) {
            throw new DomainException(
                'El usuario no existe.'
            );
        }

        if ((int) $usuario['organizacionId'] !== $organizacionId) {
            throw new DomainException(
                'El usuario no pertenece a la organización del cartón.'
            );
        }

        return $usuario;
    }
}
